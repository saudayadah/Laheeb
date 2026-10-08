<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DailyClose;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function __construct(private PostingService $posting) {}

    /**
     * Open (unpaid) posted credit invoices, oldest first.
     *
     * @return Collection<int, array{invoice: Invoice, open: string}>
     */
    public function openInvoices(?Customer $customer, ?CustomerGroup $group = null): Collection
    {
        $customerIds = $customer !== null
            ? collect([$customer->id])
            : Customer::where('customer_group_id', $group?->id ?? 0)->pluck('id');

        if ($customerIds->isEmpty()) {
            return collect();
        }

        $invoices = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', Invoice::STATUS_POSTED)
            ->where('payment_method', 'credit')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            return collect();
        }

        $allocated = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipts.status', 'posted')
            ->whereIn('receipt_allocations.invoice_id', $invoices->pluck('id'))
            ->groupBy('receipt_allocations.invoice_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as amount')
            ->pluck('amount', 'invoice_id');

        $credited = DB::table('credit_notes')
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', 'posted')
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, SUM(total) as amount')
            ->pluck('amount', 'invoice_id');

        return $invoices
            ->map(function (Invoice $invoice) use ($allocated, $credited) {
                $open = Money::subtract(
                    (string) $invoice->total,
                    Money::add((string) ($allocated[$invoice->id] ?? '0.00'), (string) ($credited[$invoice->id] ?? '0.00')),
                );

                return ['invoice' => $invoice, 'open' => $open];
            })
            ->filter(fn ($row) => Money::compare($row['open'], '0.00') > 0)
            ->values();
    }

    /**
     * Create and post a receipt. Allocation is oldest-first unless
     * $manualAllocations ([invoice_id => amount]) is given.
     */
    public function create(array $attrs, ?array $manualAllocations, User $user, ?string $idempotencyKey = null): Receipt
    {
        if ($idempotencyKey !== null) {
            $existing = Receipt::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($attrs, $manualAllocations, $user, $idempotencyKey) {
            // Locking reads FIRST: they serialize two cashiers collecting for
            // the same payer and refresh the read view, so openInvoices()
            // below always sees allocations committed a moment ago.
            $customer = ! empty($attrs['customer_id']) ? Customer::whereKey($attrs['customer_id'])->lockForUpdate()->firstOrFail() : null;
            $group = ! empty($attrs['customer_group_id']) ? CustomerGroup::whereKey($attrs['customer_group_id'])->lockForUpdate()->firstOrFail() : null;

            if ($customer === null && $group === null) {
                throw ValidationException::withMessages(['customer_id' => __('receipts.customer_required')]);
            }

            $amount = Money::add((string) $attrs['amount'], '0.00');
            $date = $attrs['receipt_date'] ?? today()->toDateString();
            $receivedBy = $attrs['received_by'] ?? $user->id;
            $method = $attrs['method'] ?? 'cash';

            $this->assertDayOpen($receivedBy, $date, $method);

            $receipt = Receipt::create([
                'series' => 'RCT',
                'number' => DocumentSequence::allocate('RCT'),
                'customer_id' => $customer?->id,
                'customer_group_id' => $group?->id,
                'receipt_date' => $date,
                'amount' => $amount,
                'method' => $method,
                'reference' => $attrs['reference'] ?? null,
                'received_by' => $receivedBy,
                'status' => 'posted',
                'idempotency_key' => $idempotencyKey,
                'notes' => $attrs['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // --- Allocate to open invoices --------------------------------
            $open = $this->openInvoices($customer, $group);
            $allocations = [];
            $remaining = $amount;

            if ($manualAllocations !== null && $manualAllocations !== []) {
                $openById = $open->keyBy(fn ($row) => $row['invoice']->id);
                foreach ($manualAllocations as $invoiceId => $alloc) {
                    $alloc = Money::add((string) $alloc, '0.00');
                    if (Money::compare($alloc, '0.00') <= 0) {
                        continue;
                    }
                    $row = $openById->get((int) $invoiceId);
                    if ($row === null || Money::compare($alloc, $row['open']) > 0 || Money::compare($alloc, $remaining) > 0) {
                        throw ValidationException::withMessages(['allocations' => __('receipts.bad_allocation')]);
                    }
                    $allocations[] = ['invoice' => $row['invoice'], 'amount' => $alloc];
                    $remaining = Money::subtract($remaining, $alloc);
                }
            } else {
                foreach ($open as $row) {
                    if (Money::compare($remaining, '0.00') <= 0) {
                        break;
                    }
                    $take = Money::compare($row['open'], $remaining) <= 0 ? $row['open'] : $remaining;
                    $allocations[] = ['invoice' => $row['invoice'], 'amount' => $take];
                    $remaining = Money::subtract($remaining, $take);
                }
            }

            foreach ($allocations as $allocation) {
                $receipt->allocations()->create([
                    'invoice_id' => $allocation['invoice']->id,
                    'amount' => $allocation['amount'],
                ]);
            }

            // --- Ledger ----------------------------------------------------
            // Customer side: reduce what each branch owes.
            if ($customer !== null) {
                $this->posting->entry(
                    LedgerEntry::CUSTOMER, $customer->id, $date,
                    debit: '0.00', credit: $amount,
                    source: $receipt,
                    description: __('ledger.receipt', ['number' => $receipt->displayNumber()]),
                    userId: $user->id,
                );
            } else {
                // Group payment: credit each branch by what was allocated to it;
                // any remainder goes on account of the oldest-debt branch.
                $perCustomer = collect($allocations)
                    ->groupBy(fn ($a) => $a['invoice']->customer_id)
                    ->map(fn ($rows) => Money::sum(collect($rows)->pluck('amount')));

                if (Money::compare($remaining, '0.00') > 0) {
                    $fallback = $open->first()['invoice']->customer_id
                        ?? Customer::where('customer_group_id', $group->id)->orderBy('id')->value('id');

                    if ($fallback === null) {
                        throw ValidationException::withMessages(['customer_id' => __('receipts.customer_required')]);
                    }

                    $perCustomer[$fallback] = Money::add((string) ($perCustomer[$fallback] ?? '0.00'), $remaining);
                }

                foreach ($perCustomer as $customerId => $credit) {
                    $this->posting->entry(
                        LedgerEntry::CUSTOMER, (int) $customerId, $date,
                        debit: '0.00', credit: $credit,
                        source: $receipt,
                        description: __('ledger.receipt', ['number' => $receipt->displayNumber()]),
                        userId: $user->id,
                    );
                }
            }

            // Cash side: where the money physically went.
            [$accountType, $accountId] = $this->cashDestination($receipt);
            $this->posting->entry(
                $accountType, $accountId, $date,
                debit: $amount, credit: '0.00',
                source: $receipt,
                description: __('ledger.receipt', ['number' => $receipt->displayNumber()]),
                userId: $user->id,
            );

            activity()->causedBy($user)->performedOn($receipt)->log('receipt.posted');

            return $receipt;
        });
    }

    public function void(Receipt $receipt, User $user, string $reason): Receipt
    {
        return DB::transaction(function () use ($receipt, $user, $reason) {
            $receipt = Receipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($receipt->status === 'void') {
                return $receipt;
            }

            // Once that day's cash was counted and handed over, the receipt
            // cannot be unwound — the physical money already moved.
            $this->assertDayOpen($receipt->received_by, (string) $receipt->receipt_date, $receipt->method);

            foreach ($receipt->ledgerEntries()->get() as $entry) {
                $this->posting->entry(
                    $entry->account_type, (int) $entry->account_id, today(),
                    debit: (string) $entry->credit, credit: (string) $entry->debit,
                    source: $receipt,
                    description: __('ledger.void', ['number' => $receipt->displayNumber(), 'reason' => $reason]),
                    userId: $user->id,
                );
            }

            $receipt->allocations()->delete();
            $receipt->update(['status' => 'void', 'void_reason' => $reason]);

            activity()->causedBy($user)->performedOn($receipt)->withProperties(['reason' => $reason])->log('receipt.voided');

            return $receipt;
        });
    }

    /** @return array{0: string, 1: int} */
    private function cashDestination(Receipt $receipt): array
    {
        if (in_array($receipt->method, ['mada', 'transfer'], true)) {
            return [LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID];
        }

        $receiver = $receipt->received_by ? User::find($receipt->received_by) : null;

        if ($receiver !== null && $receiver->hasRole('driver')) {
            return [LedgerEntry::DRIVER, $receiver->id];
        }

        return [LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID];
    }

    private function assertDayOpen(?int $receivedBy, string $date, string $method): void
    {
        if ($method !== 'cash') {
            return;
        }

        $receiver = $receivedBy !== null ? User::find($receivedBy) : null;

        if ($receiver !== null && $receiver->hasRole('driver')) {
            $locked = DailyClose::where('closeable_type', DailyClose::TYPE_DRIVER)
                ->where('closeable_id', $receivedBy)
                ->whereDate('close_date', $date)
                ->where('status', 'approved')
                ->exists();

            if ($locked) {
                throw ValidationException::withMessages(['receipt' => __('closes.day_locked')]);
            }

            return;
        }

        // Office cash lands in the box: an approved counter close locks it too.
        $locked = DailyClose::where('closeable_type', DailyClose::TYPE_COUNTER)
            ->where('closeable_id', 0)
            ->whereDate('close_date', $date)
            ->where('status', 'approved')
            ->exists();

        if ($locked) {
            throw ValidationException::withMessages(['receipt' => __('closes.day_locked')]);
        }
    }
}
