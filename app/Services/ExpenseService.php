<?php

namespace App\Services;

use App\Models\DailyClose;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private PostingService $posting) {}

    public function create(array $attrs, User $user, ?UploadedFile $photo = null): Expense
    {
        return DB::transaction(function () use ($attrs, $user, $photo) {
            $amount = Money::add((string) $attrs['amount'], '0.00');
            $threshold = (string) AppSettings::get('expense_approval_threshold', '500');

            // Big expenses wait for approval unless the creator can approve.
            $needsApproval = Money::compare($amount, $threshold) > 0 && ! $user->can('expenses.approve');

            if (($attrs['paid_from'] ?? '') === 'driver_cash' && empty($attrs['paid_by'])) {
                throw ValidationException::withMessages(['paid_by' => __('expenses.payer_required')]);
            }

            if (($attrs['paid_from'] ?? '') === 'supplier_credit' && empty($attrs['supplier_id'])) {
                throw ValidationException::withMessages(['supplier_id' => __('expenses.supplier_required')]);
            }

            $expense = Expense::create([
                'expense_date' => $attrs['expense_date'] ?? today()->toDateString(),
                'expense_category_id' => $attrs['expense_category_id'],
                'amount' => $amount,
                'vat_amount' => $attrs['vat_amount'] ?? null,
                'paid_from' => $attrs['paid_from'],
                'paid_by' => $attrs['paid_by'] ?? null,
                'supplier_id' => $attrs['supplier_id'] ?? null,
                'vehicle_id' => $attrs['vehicle_id'] ?? null,
                'status' => $needsApproval ? 'pending' : 'approved',
                'note' => $attrs['note'] ?? null,
                'receipt_photo' => $photo?->store('expense-receipts'),
                'from_sheet' => (bool) ($attrs['from_sheet'] ?? false),
                'recurring_expense_id' => $attrs['recurring_expense_id'] ?? null,
                'created_by' => $user->id,
                'approved_by' => $needsApproval ? null : $user->id,
                'approved_at' => $needsApproval ? null : now(),
            ]);

            if ($expense->isApproved()) {
                $this->postLedger($expense, $user);
            }

            activity()->causedBy($user)->performedOn($expense)->log('expense.created');

            return $expense;
        });
    }

    public function approve(Expense $expense, User $approver): Expense
    {
        return DB::transaction(function () use ($expense, $approver) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->status !== 'pending') {
                return $expense;
            }

            $expense->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->postLedger($expense, $approver);

            activity()->causedBy($approver)->performedOn($expense)->log('expense.approved');

            return $expense;
        });
    }

    public function void(Expense $expense, User $user, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $user, $reason) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->status === 'void') {
                return $expense;
            }

            $wasApproved = $expense->isApproved();

            $expense->update(['status' => 'void', 'void_reason' => $reason]);

            if ($wasApproved) {
                foreach ($expense->ledgerEntries()->get() as $entry) {
                    $this->posting->entry(
                        $entry->account_type, (int) $entry->account_id, today(),
                        debit: (string) $entry->credit, credit: (string) $entry->debit,
                        source: $expense,
                        description: __('ledger.void', ['number' => "EXP-{$expense->id}", 'reason' => $reason]),
                        userId: $user->id,
                    );
                }
            }

            activity()->causedBy($user)->performedOn($expense)->withProperties(['reason' => $reason])->log('expense.voided');

            return $expense;
        });
    }

    /** Where the money came out of. */
    private function postLedger(Expense $expense, User $user): void
    {
        // After that day's cash was counted and approved, no expense may
        // retroactively shrink the custody/box it was counted from.
        if ($expense->paid_from === 'driver_cash') {
            $locked = DailyClose::where('closeable_type', DailyClose::TYPE_DRIVER)
                ->where('closeable_id', $expense->paid_by)
                ->whereDate('close_date', $expense->expense_date)
                ->where('status', 'approved')
                ->exists();

            if ($locked) {
                throw ValidationException::withMessages(['expense' => __('closes.day_locked')]);
            }
        } elseif ($expense->paid_from === 'counter_cash') {
            $locked = DailyClose::where('closeable_type', DailyClose::TYPE_COUNTER)
                ->where('closeable_id', 0)
                ->whereDate('close_date', $expense->expense_date)
                ->where('status', 'approved')
                ->exists();

            if ($locked) {
                throw ValidationException::withMessages(['expense' => __('closes.day_locked')]);
            }
        }

        [$type, $id] = match ($expense->paid_from) {
            'driver_cash' => [LedgerEntry::DRIVER, (int) $expense->paid_by],
            'bank' => [LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID],
            'supplier_credit' => [LedgerEntry::SUPPLIER, (int) $expense->supplier_id],
            default => [LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID],
        };

        $this->posting->entry(
            $type, $id, $expense->expense_date,
            debit: '0.00', credit: (string) $expense->amount,
            source: $expense,
            description: __('ledger.expense', ['category' => $expense->category->name_ar]),
            userId: $user->id,
        );
    }

    public function paySupplier(Supplier $supplier, array $attrs, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $attrs, $user) {
            $amount = Money::add((string) $attrs['amount'], '0.00');
            $date = $attrs['payment_date'] ?? today()->toDateString();

            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'payment_date' => $date,
                'amount' => $amount,
                'method' => $attrs['method'] ?? 'bank',
                'reference' => $attrs['reference'] ?? null,
                'notes' => $attrs['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // We owe less...
            $this->posting->entry(
                LedgerEntry::SUPPLIER, $supplier->id, $date,
                debit: $amount, credit: '0.00',
                source: $payment,
                description: __('ledger.supplier_payment', ['name' => $supplier->name]),
                userId: $user->id,
            );

            // ...and the money left the box or the bank.
            [$type, $id] = ($attrs['method'] ?? 'bank') === 'counter_cash'
                ? [LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID]
                : [LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID];

            $this->posting->entry(
                $type, $id, $date,
                debit: '0.00', credit: $amount,
                source: $payment,
                description: __('ledger.supplier_payment', ['name' => $supplier->name]),
                userId: $user->id,
            );

            activity()->causedBy($user)->performedOn($payment)->log('supplier_payment.created');

            return $payment;
        });
    }

    /** Create this month's pending drafts from the recurring definitions. */
    public function generateRecurring(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $period = $now->format('Y-m');
        $count = 0;

        $due = RecurringExpense::where('active', true)
            ->where('day_of_month', '<=', $now->day)
            ->where(fn ($q) => $q->whereNull('last_generated_period')->orWhere('last_generated_period', '!=', $period))
            ->get();

        foreach ($due as $recurring) {
            DB::transaction(function () use ($recurring, $now, $period, &$count) {
                $date = $now->copy()->day(min($recurring->day_of_month, $now->daysInMonth));

                Expense::create([
                    'expense_date' => $date,
                    'expense_category_id' => $recurring->expense_category_id,
                    'amount' => $recurring->amount,
                    'paid_from' => $recurring->paid_from,
                    'supplier_id' => $recurring->supplier_id,
                    'status' => 'pending', // the accountant confirms the draft
                    'note' => $recurring->name,
                    'recurring_expense_id' => $recurring->id,
                ]);

                $recurring->update(['last_generated_period' => $period]);
                $count++;
            });
        }

        return $count;
    }
}
