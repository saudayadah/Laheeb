<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ReceivablesService
{
    public function __construct(private ReceiptService $receipts) {}

    /**
     * Receivables per customer with aging buckets (FIFO: payments cover the
     * oldest invoices first). Only customers with a positive balance.
     */
    public function aging(): array
    {
        $balances = LedgerEntry::query()
            ->where('account_type', LedgerEntry::CUSTOMER)
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as balance')
            ->havingRaw('SUM(debit) - SUM(credit) > 0.004')
            ->pluck('balance', 'account_id');

        if ($balances->isEmpty()) {
            return ['rows' => [], 'totals' => $this->emptyTotals()];
        }

        // withTrashed: a soft-deleted customer with a live balance must still
        // appear here, or this screen drifts from the dashboard's ledger sum.
        $customers = Customer::withTrashed()
            ->with('group:id,name')
            ->whereIn('id', $balances->keys())
            ->get(['id', 'code', 'name', 'customer_group_id', 'credit_days', 'phone', 'whatsapp'])
            ->keyBy('id');

        $lastPayments = Receipt::where('status', 'posted')
            ->whereIn('customer_id', $balances->keys())
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(receipt_date) as last_date')
            ->pluck('last_date', 'customer_id');

        $lastInvoices = DB::table('invoices')
            ->where('status', 'posted')
            ->whereIn('customer_id', $balances->keys())
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(invoice_date) as last_date')
            ->pluck('last_date', 'customer_id');

        $rows = [];
        $totals = $this->emptyTotals();

        foreach ($balances as $customerId => $balance) {
            $customer = $customers->get($customerId);
            if ($customer === null) {
                continue;
            }

            $balance = number_format((float) $balance, 2, '.', '');
            $buckets = $this->bucketize($customer, $balance);

            $rows[] = [
                'customer' => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'group' => $customer->group?->only(['id', 'name']),
                    'phone' => $customer->phone,
                    'whatsapp' => $customer->whatsapp,
                ],
                'balance' => $balance,
                'buckets' => $buckets,
                'last_payment' => $lastPayments[$customerId] ?? null,
                'last_invoice' => $lastInvoices[$customerId] ?? null,
                'overdue' => Money::compare(
                    Money::add($buckets['b60'], Money::add($buckets['b90'], $buckets['b90p'])),
                    '0.00',
                ) > 0,
            ];

            $totals['balance'] = Money::add($totals['balance'], $balance);
            foreach (['b30', 'b60', 'b90', 'b90p'] as $bucket) {
                $totals[$bucket] = Money::add($totals[$bucket], $buckets[$bucket]);
            }
        }

        usort($rows, fn ($a, $b) => Money::compare($b['balance'], $a['balance']));

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** FIFO buckets 0-30 / 31-60 / 61-90 / 90+ for one customer. */
    private function bucketize(Customer $customer, string $balance): array
    {
        $open = $this->receipts->openInvoices($customer);

        // If unallocated credit exists, apply it to the oldest open amounts first.
        $sumOpen = Money::sum($open->pluck('open'));
        $excess = Money::subtract($sumOpen, $balance);

        $buckets = ['b30' => '0.00', 'b60' => '0.00', 'b90' => '0.00', 'b90p' => '0.00'];

        foreach ($open as $row) {
            $amount = $row['open'];

            if (Money::compare($excess, '0.00') > 0) {
                $eat = Money::compare($excess, $amount) >= 0 ? $amount : $excess;
                $amount = Money::subtract($amount, $eat);
                $excess = Money::subtract($excess, $eat);
            }

            if (Money::isZero($amount)) {
                continue;
            }

            $age = (int) $row['invoice']->invoice_date->diffInDays(today());
            $key = $age <= 30 ? 'b30' : ($age <= 60 ? 'b60' : ($age <= 90 ? 'b90' : 'b90p'));
            $buckets[$key] = Money::add($buckets[$key], $amount);
        }

        // Balance not represented by open invoices (e.g. opening balances)
        // is treated as current.
        $bucketed = Money::sum($buckets);
        if (Money::compare($balance, $bucketed) > 0) {
            $buckets['b30'] = Money::add($buckets['b30'], Money::subtract($balance, $bucketed));
        }

        return $buckets;
    }

    private function emptyTotals(): array
    {
        return ['balance' => '0.00', 'b30' => '0.00', 'b60' => '0.00', 'b90' => '0.00', 'b90p' => '0.00'];
    }
}
