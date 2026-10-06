<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * The owner's monthly sheet: per month of a year â€”
     * total / cash / credit / mada sales (net of credit notes),
     * expenses (split), net and net-cash.
     */
    public function monthly(int $year): array
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = fn (string $column) => $driver === 'sqlite'
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";

        // Sales by month x method.
        $sales = Invoice::query()
            ->where('status', Invoice::STATUS_POSTED)
            ->whereYear('invoice_date', $year)
            ->groupBy(DB::raw($monthExpr('invoice_date')), 'payment_method')
            ->selectRaw("{$monthExpr('invoice_date')} as m, payment_method, COALESCE(SUM(total), 0) as sum")
            ->get();

        // Credit notes reduce the method of their original invoice (cash if unknown).
        $creditNotes = DB::table('credit_notes')
            ->leftJoin('invoices', 'invoices.id', '=', 'credit_notes.invoice_id')
            ->where('credit_notes.status', 'posted')
            ->whereRaw($driver === 'sqlite'
                ? "CAST(strftime('%Y', credit_notes.note_date) AS INTEGER) = ?"
                : 'YEAR(credit_notes.note_date) = ?', [$year])
            ->groupBy(DB::raw($monthExpr('credit_notes.note_date')), 'invoices.payment_method')
            ->selectRaw("{$monthExpr('credit_notes.note_date')} as m, invoices.payment_method, COALESCE(SUM(credit_notes.total), 0) as sum")
            ->get();

        // Expenses by month x source.
        $expenses = Expense::query()
            ->where('status', 'approved')
            ->whereYear('expense_date', $year)
            ->groupBy(DB::raw($monthExpr('expense_date')), 'paid_from')
            ->selectRaw("{$monthExpr('expense_date')} as m, paid_from, COALESCE(SUM(amount), 0) as sum")
            ->get();

        // Salaries and rent split out, like the owner's current sheet.
        $specialIds = ExpenseCategory::whereIn('name_ar', ['Ø±ÙˆØ§ØªØ¨', 'Ø¥ÙŠØ¬Ø§Ø±Ø§Øª'])->pluck('id', 'name_ar');
        $specials = Expense::query()
            ->where('status', 'approved')
            ->whereYear('expense_date', $year)
            ->whereIn('expense_category_id', $specialIds->values())
            ->groupBy(DB::raw($monthExpr('expense_date')), 'expense_category_id')
            ->selectRaw("{$monthExpr('expense_date')} as m, expense_category_id, COALESCE(SUM(amount), 0) as sum")
            ->get();

        $collections = Receipt::query()
            ->where('status', 'posted')
            ->whereYear('receipt_date', $year)
            ->groupBy(DB::raw($monthExpr('receipt_date')))
            ->selectRaw("{$monthExpr('receipt_date')} as m, COALESCE(SUM(amount), 0) as sum")
            ->pluck('sum', 'm');

        $rows = [];
        foreach (range(1, 12) as $month) {
            $methodSum = fn ($set, $method) => (string) ($set->first(fn ($r) => (int) $r->m === $month && $r->payment_method === $method)?->sum ?? '0');

            $cash = Money::subtract($methodSum($sales, 'cash'), $methodSum($creditNotes, 'cash'));
            // Unlinked credit notes land on cash as well.
            $unlinked = (string) ($creditNotes->first(fn ($r) => (int) $r->m === $month && $r->payment_method === null)?->sum ?? '0');
            $cash = Money::subtract($cash, $unlinked);
            $credit = Money::subtract($methodSum($sales, 'credit'), $methodSum($creditNotes, 'credit'));
            $mada = Money::subtract($methodSum($sales, 'mada'), $methodSum($creditNotes, 'mada'));
            $total = Money::sum([$cash, $credit, $mada]);

            $sourceSum = fn ($source) => (string) ($expenses->first(fn ($r) => (int) $r->m === $month && $r->paid_from === $source)?->sum ?? '0');
            $expensesCash = Money::add($sourceSum('driver_cash'), $sourceSum('counter_cash'));
            $expensesBank = $sourceSum('bank');
            $expensesCreditSupp = $sourceSum('supplier_credit');
            $expensesTotal = Money::sum([$expensesCash, $expensesBank, $expensesCreditSupp]);

            $specialSum = fn ($name) => (string) ($specials->first(
                fn ($r) => (int) $r->m === $month && (int) $r->expense_category_id === (int) ($specialIds[$name] ?? 0),
            )?->sum ?? '0');

            $rows[] = [
                'month' => $month,
                'total' => $total,
                'cash' => $cash,
                'credit' => $credit,
                'mada' => $mada,
                'collections' => Money::add((string) ($collections[$month] ?? '0'), '0.00'),
                'expenses_total' => $expensesTotal,
                'expenses_cash' => $expensesCash,
                'expenses_bank' => $expensesBank,
                'salaries' => $specialSum('Ø±ÙˆØ§ØªØ¨'),
                'rent' => $specialSum('Ø¥ÙŠØ¬Ø§Ø±Ø§Øª'),
                'net' => Money::subtract($total, $expensesTotal),
                'net_cash' => Money::subtract(Money::add($cash, $mada), $expensesCash),
            ];
        }

        return $rows;
    }

    /** Customer statement: opening balance + every movement with a running balance. */
    public function statement(Customer $customer, Carbon $from, Carbon $to): array
    {
        $openingRow = LedgerEntry::query()
            ->where('account_type', LedgerEntry::CUSTOMER)
            ->where('account_id', $customer->id)
            ->whereDate('entry_date', '<', $from)
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        $opening = Money::subtract((string) $openingRow->d, (string) $openingRow->c);

        $entries = LedgerEntry::query()
            ->where('account_type', LedgerEntry::CUSTOMER)
            ->where('account_id', $customer->id)
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = $opening;
        $rows = $entries->map(function (LedgerEntry $entry) use (&$running) {
            $running = Money::add($running, Money::subtract((string) $entry->debit, (string) $entry->credit));

            return [
                'id' => $entry->id,
                'date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'debit' => (string) $entry->debit,
                'credit' => (string) $entry->credit,
                'balance' => $running,
            ];
        });

        return [
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
        ];
    }

    public function salesByProduct(Carbon $from, Carbon $to): array
    {
        return DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_lines.product_id')
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->groupBy('invoice_lines.product_id', 'products.name_ar')
            ->selectRaw("COALESCE(products.name_ar, 'Ø£Ø®Ø±Ù‰') as name, SUM(invoice_lines.qty) as qty, SUM(invoice_lines.line_total) as total")
            ->orderByDesc(DB::raw('SUM(invoice_lines.line_total)'))
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => (int) $row->qty,
                'total' => Money::add((string) $row->total, '0.00'),
            ])
            ->all();
    }

    public function salesByCustomer(Carbon $from, Carbon $to, int $limit = 30): array
    {
        return DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->groupBy('invoices.customer_id', 'customers.name', 'customers.code')
            ->selectRaw('customers.name, customers.code, SUM(invoice_lines.qty) as qty, SUM(invoice_lines.line_total) as total')
            ->orderByDesc(DB::raw('SUM(invoice_lines.line_total)'))
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'code' => $row->code,
                'qty' => (int) $row->qty,
                'total' => Money::add((string) $row->total, '0.00'),
            ])
            ->all();
    }

    public function salesByRoute(Carbon $from, Carbon $to): array
    {
        return DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('delivery_routes', 'delivery_routes.id', '=', 'customers.delivery_route_id')
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->groupBy('customers.delivery_route_id', 'delivery_routes.name')
            ->selectRaw('delivery_routes.name, SUM(invoice_lines.qty) as qty, SUM(invoice_lines.line_total) as total')
            ->orderByDesc(DB::raw('SUM(invoice_lines.line_total)'))
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name ?? __('orders.no_route'),
                'qty' => (int) $row->qty,
                'total' => Money::add((string) $row->total, '0.00'),
            ])
            ->all();
    }

    /** Active wholesale customers with no order for N days â€” catch them early. */
    public function inactiveCustomers(int $days = 3): array
    {
        $cutoff = today()->subDays($days);

        $lastInvoices = Invoice::query()
            ->where('status', Invoice::STATUS_POSTED)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(invoice_date) as last_date')
            ->pluck('last_date', 'customer_id');

        return Customer::query()
            ->where('active', true)
            ->where('type', '!=', 'walkin')
            ->with('route:id,name')
            ->get(['id', 'code', 'name', 'phone', 'delivery_route_id'])
            ->filter(function (Customer $customer) use ($lastInvoices, $cutoff) {
                $last = $lastInvoices[$customer->id] ?? null;

                return $last === null || Carbon::parse($last)->lt($cutoff);
            })
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'route' => $customer->route?->name,
                'last_invoice' => isset($lastInvoices[$customer->id])
                    ? Carbon::parse($lastInvoices[$customer->id])->toDateString()
                    : null,
                'balance' => LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id),
            ])
            ->sortByDesc(fn ($row) => (float) $row['balance'])
            ->values()
            ->take(50)
            ->all();
    }

    /** Customers whose weekly volume dropped by more than X%. */
    public function volumeDrops(int $percent = 30, int $minWeeklyLoaves = 100): array
    {
        $week = fn (Carbon $from, Carbon $to) => DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->whereNotNull('invoices.customer_id')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->groupBy('invoices.customer_id')
            ->selectRaw('invoices.customer_id, SUM(invoice_lines.qty) as qty')
            ->pluck('qty', 'customer_id');

        $current = $week(today()->subDays(6), today());
        $previous = $week(today()->subDays(13), today()->subDays(7));

        $customers = Customer::whereIn('id', $previous->keys())->pluck('name', 'id');

        $rows = [];
        foreach ($previous as $customerId => $prevQty) {
            if ((int) $prevQty < $minWeeklyLoaves) {
                continue;
            }

            $currQty = (int) ($current[$customerId] ?? 0);
            $drop = (int) round((($prevQty - $currQty) / $prevQty) * 100);

            if ($drop >= $percent) {
                $rows[] = [
                    'id' => (int) $customerId,
                    'name' => $customers[$customerId] ?? 'ØŸ',
                    'previous' => (int) $prevQty,
                    'current' => $currQty,
                    'drop' => $drop,
                ];
            }
        }

        usort($rows, fn ($a, $b) => $b['drop'] <=> $a['drop']);

        return array_slice($rows, 0, 50);
    }

    /** Who is still holding cash (ØºÙŠØ± Ù…Ø­ØµÙ„ per driver). */
    public function custodyByDriver(): array
    {
        return LedgerEntry::query()
            ->where('account_type', LedgerEntry::DRIVER)
            ->join('users', 'users.id', '=', 'ledger_entries.account_id')
            ->groupBy('ledger_entries.account_id', 'users.name')
            ->selectRaw('users.name, SUM(ledger_entries.debit) - SUM(ledger_entries.credit) as balance')
            ->havingRaw('ABS(SUM(ledger_entries.debit) - SUM(ledger_entries.credit)) > 0.004')
            ->orderByDesc(DB::raw('SUM(ledger_entries.debit) - SUM(ledger_entries.credit)'))
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'balance' => number_format((float) $row->balance, 2, '.', ''),
            ])
            ->all();
    }

    public function collectionsByDriver(Carbon $from, Carbon $to): array
    {
        return Receipt::query()
            ->where('status', 'posted')
            ->whereDate('receipt_date', '>=', $from)
            ->whereDate('receipt_date', '<=', $to)
            ->whereNotNull('received_by')
            ->join('users', 'users.id', '=', 'receipts.received_by')
            ->groupBy('receipts.received_by', 'users.name')
            ->selectRaw("users.name as name, COALESCE(SUM(receipts.amount), 0) as total, COUNT(*) as cnt, COALESCE(SUM(CASE WHEN receipts.method = 'cash' THEN receipts.amount ELSE 0 END), 0) as cash")
            ->orderByDesc(DB::raw('SUM(receipts.amount)'))
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'total' => Money::add((string) $row->total, '0.00'),
                'cash' => Money::add((string) $row->cash, '0.00'),
                'count' => (int) $row->cnt,
            ])
            ->all();
    }
}
