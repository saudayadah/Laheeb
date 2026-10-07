<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\EmployeeLeave;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\RawMaterial;
use App\Models\RawMaterialMovement;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\VehicleMaintenance;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $money = null;

        if ($user->can('reports.view')) {
            $salesRows = Invoice::whereDate('invoice_date', today())
                ->where('status', Invoice::STATUS_POSTED)
                ->select('payment_method', DB::raw('COALESCE(SUM(total), 0) as sum'))
                ->groupBy('payment_method')
                ->pluck('sum', 'payment_method');

            $collectionsToday = Receipt::whereDate('receipt_date', today())
                ->where('status', 'posted')
                ->sum('amount');

            // One ledger scan feeds both the receivables card and the attention panel.
            $customerBalances = LedgerEntry::query()
                ->where('account_type', LedgerEntry::CUSTOMER)
                ->groupBy('account_id')
                ->selectRaw('account_id, SUM(debit) - SUM(credit) as balance')
                ->havingRaw('SUM(debit) - SUM(credit) > 0.004')
                ->pluck('balance', 'account_id');

            $receivables = $customerBalances->sum();

            $custody = LedgerEntry::query()
                ->where('account_type', LedgerEntry::DRIVER)
                ->groupBy('account_id')
                ->selectRaw('account_id, SUM(debit) - SUM(credit) as balance')
                ->havingRaw('SUM(debit) - SUM(credit) > 0.004')
                ->get()
                ->sum('balance');

            $salesToday = (float) $salesRows->sum();

            // Last 14 days of posted sales for the trend chart.
            $daily = Invoice::where('status', Invoice::STATUS_POSTED)
                ->whereDate('invoice_date', '>=', today()->subDays(13))
                ->groupBy(DB::raw('DATE(invoice_date)'))
                ->selectRaw('DATE(invoice_date) as d, COALESCE(SUM(total), 0) as s')
                ->pluck('s', 'd');

            $trend = [];
            foreach (range(13, 0) as $daysAgo) {
                $day = today()->subDays($daysAgo);
                $trend[] = [
                    'date' => $day->toDateString(),
                    'label' => $day->format('d/m'),
                    'total' => (float) ($daily[$day->toDateString()] ?? 0),
                ];
            }

            $money = [
                'trend' => $trend,
                'sales_today' => (string) $salesToday,
                'cash_today' => (string) ($salesRows['cash'] ?? '0'),
                'credit_today' => (string) ($salesRows['credit'] ?? '0'),
                'mada_today' => (string) ($salesRows['mada'] ?? '0'),
                'collections_today' => (string) $collectionsToday,
                'receivables' => (string) $receivables,
                'custody' => (string) $custody,
                'cash_box' => LedgerEntry::balance(LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID),
            ];
        }

        return Inertia::render('dashboard', [
            'money' => $money,
            'stats' => [
                'customers' => Customer::where('active', true)->count(),
                'orders_today' => Order::whereDate('order_date', today())->whereHas('lines')->count(),
                'invoices_today' => Invoice::whereDate('invoice_date', today())->where('status', Invoice::STATUS_POSTED)->count(),
            ],
            'attention' => $user->can('reports.view')
                ? $this->attention($user, $customerBalances ?? collect())
                : null,
        ]);
    }

    /**
     * The owner's morning checklist: everything that needs a decision or
     * an eye today, with a direct link to the screen that fixes it.
     *
     * @return array<int, array{key: string, count: int, amount?: string, href: string, tone: string}>
     */
    /**
     * @param  Collection<int|string, mixed>  $balances  customer ledger balances (id => balance > 0)
     */
    private function attention(User $user, Collection $balances): array
    {
        $items = [];

        // Unpaid credit invoices past EACH customer's own credit window,
        // capped by the customer's live balance so on-account payments
        // and standalone credit notes stop a settled customer from alarming.
        $defaultDays = (int) AppSettings::defaultCreditDays();

        $allocated = ReceiptAllocation::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipts.status', 'posted')
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, SUM(receipt_allocations.amount) as paid');

        $credited = CreditNote::query()
            ->where('status', 'posted')
            ->whereNotNull('invoice_id')
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, SUM(total) as credited');

        $overdueRows = Invoice::query()
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoinSub($allocated, 'al', 'al.invoice_id', '=', 'invoices.id')
            ->leftJoinSub($credited, 'cn', 'cn.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->where('invoices.payment_method', 'credit')
            ->whereDate('invoices.invoice_date', '<', today())
            ->selectRaw('invoices.customer_id, invoices.invoice_date, customers.credit_days,'
                .' invoices.total - COALESCE(al.paid, 0) - COALESCE(cn.credited, 0) as remaining')
            ->get();

        $byCustomer = [];
        foreach ($overdueRows as $row) {
            if ((float) $row->remaining <= 0.004) {
                continue;
            }
            $window = $row->credit_days !== null ? (int) $row->credit_days : $defaultDays;
            if (! Carbon::parse($row->invoice_date)->lt(today()->subDays($window))) {
                continue;
            }
            $byCustomer[$row->customer_id]['sum'] = ($byCustomer[$row->customer_id]['sum'] ?? 0) + (float) $row->remaining;
            $byCustomer[$row->customer_id]['count'] = ($byCustomer[$row->customer_id]['count'] ?? 0) + 1;
        }

        $overdueCount = 0;
        $overdueAmount = 0.0;
        foreach ($byCustomer as $customerId => $agg) {
            $balance = (float) ($balances[$customerId] ?? 0);
            if ($balance <= 0.004) {
                continue;
            }
            $overdueAmount += min($agg['sum'], $balance);
            $overdueCount += $agg['count'];
        }

        if ($overdueCount > 0) {
            $items[] = [
                'key' => 'overdue_invoices',
                'count' => $overdueCount,
                'amount' => number_format($overdueAmount, 2, '.', ''),
                'href' => '/receivables',
                'tone' => 'red',
            ];
        }

        // Customers whose own limit is blown (group scopes are enforced at posting time).
        $overLimit = Customer::query()
            ->whereIn('id', $balances->keys())
            ->whereNotNull('credit_limit')
            ->where('credit_limit', '>', 0)
            ->get(['id', 'credit_limit'])
            ->filter(fn (Customer $c) => (float) $balances[$c->id] > (float) $c->credit_limit + 0.004)
            ->count();

        if ($overLimit > 0) {
            $items[] = ['key' => 'over_limit', 'count' => $overLimit, 'href' => '/receivables', 'tone' => 'red'];
        }

        if (($pendingExpenses = Expense::where('status', 'pending')->count()) > 0) {
            // The wide from= keeps the linked page showing every counted item,
            // not just the current month's.
            $items[] = ['key' => 'pending_expenses', 'count' => $pendingExpenses, 'href' => '/expenses?status=pending&from=2000-01-01', 'tone' => 'amber'];
        }

        if (($pendingAdvances = Advance::where('status', 'pending')->count()) > 0) {
            $items[] = ['key' => 'pending_advances', 'count' => $pendingAdvances, 'href' => '/advances', 'tone' => 'amber'];
        }

        if (($unpaidRuns = PayrollRun::whereIn('status', ['draft', 'reviewed', 'approved'])->count()) > 0) {
            $items[] = ['key' => 'unpaid_payroll', 'count' => $unpaidRuns, 'href' => '/payroll', 'tone' => 'amber'];
        }

        // One grouped query instead of two SUMs per material.
        $onHand = RawMaterialMovement::query()
            ->selectRaw("raw_material_id, SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END) as on_hand")
            ->groupBy('raw_material_id')
            ->pluck('on_hand', 'raw_material_id');

        $lowMaterials = RawMaterial::query()
            ->where('active', true)
            ->whereNotNull('reorder_level')
            ->get(['id', 'reorder_level'])
            ->filter(fn ($m) => (float) ($onHand[$m->id] ?? 0) <= (float) $m->reorder_level)
            ->count();

        if ($lowMaterials > 0) {
            $items[] = ['key' => 'low_materials', 'count' => $lowMaterials, 'href' => '/materials', 'tone' => 'amber'];
        }

        // Only the LATEST maintenance row per vehicle decides due-soon,
        // matching the vehicles screen exactly.
        $dueMaintenance = VehicleMaintenance::query()
            ->whereIn('id', VehicleMaintenance::selectRaw('MAX(id)')->groupBy('vehicle_id'))
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', today()->addDays(7))
            ->count();

        if ($dueMaintenance > 0) {
            $items[] = ['key' => 'vehicles_due', 'count' => $dueMaintenance, 'href' => '/vehicles', 'tone' => 'amber'];
        }

        $overdueLeaves = EmployeeLeave::query()
            ->whereNull('actual_return')
            ->whereDate('expected_return', '<', today())
            ->count();

        if ($overdueLeaves > 0) {
            $items[] = ['key' => 'overdue_leaves', 'count' => $overdueLeaves, 'href' => '/leaves', 'tone' => 'amber'];
        }

        // The orders screen needs orders.manage — hide the row from roles
        // that would only hit a 403.
        if ($user->can('orders.manage')) {
            $unconfirmedOrders = Order::whereDate('order_date', today())
                ->where('status', '!=', Order::STATUS_CONFIRMED)
                ->whereHas('lines')
                ->count();

            if ($unconfirmedOrders > 0) {
                $items[] = ['key' => 'unconfirmed_orders', 'count' => $unconfirmedOrders, 'href' => '/orders', 'tone' => 'blue'];
            }
        }

        return $items;
    }
}
