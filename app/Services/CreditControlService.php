<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Support\AppSettings;
use App\Support\Money;

class CreditControlService
{
    /**
     * May this customer take `additionalAmount` more on credit?
     * Returns null when allowed, otherwise a translation key describing why not.
     */
    public function check(Customer $customer, string $additionalAmount = '0.00'): ?string
    {
        $group = $customer->group;
        $groupScope = $group !== null && $group->credit_scope === 'group';

        // --- Limit ---------------------------------------------------------
        $limit = $groupScope
            ? ($group->credit_limit !== null ? (string) $group->credit_limit : null)
            : ($customer->credit_limit !== null ? (string) $customer->credit_limit : ($group?->credit_limit !== null ? (string) $group->credit_limit : null));

        $balance = $groupScope
            ? $this->groupBalance($group->id)
            : LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id);

        if ($limit !== null && Money::compare(Money::add($balance, $additionalAmount), $limit) > 0) {
            return 'credit.over_limit';
        }

        // --- Overdue (FIFO approximation: the balance pays oldest first) ----
        $creditDays = $customer->credit_days ?? AppSettings::defaultCreditDays();
        $cutoff = today()->subDays($creditDays);

        if (Money::compare($balance, '0.00') > 0) {
            $recentInvoices = Invoice::query()
                ->where('status', Invoice::STATUS_POSTED)
                ->where('payment_method', 'credit')
                ->whereDate('invoice_date', '>', $cutoff)
                ->when(
                    $groupScope,
                    fn ($q) => $q->whereIn('customer_id', Customer::where('customer_group_id', $group->id)->pluck('id')),
                    fn ($q) => $q->where('customer_id', $customer->id),
                )
                ->sum('total');

            if (Money::compare($balance, (string) $recentInvoices) > 0) {
                return 'credit.overdue';
            }
        }

        return null;
    }

    public function groupBalance(int $groupId): string
    {
        $ids = Customer::where('customer_group_id', $groupId)->pluck('id');

        if ($ids->isEmpty()) {
            return '0.00';
        }

        $row = LedgerEntry::query()
            ->where('account_type', LedgerEntry::CUSTOMER)
            ->whereIn('account_id', $ids)
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        return Money::subtract((string) $row->d, (string) $row->c);
    }
}
