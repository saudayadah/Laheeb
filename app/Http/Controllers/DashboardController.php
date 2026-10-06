<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Receipt;
use Illuminate\Http\Request;
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

            $receivables = LedgerEntry::query()
                ->where('account_type', LedgerEntry::CUSTOMER)
                ->groupBy('account_id')
                ->selectRaw('account_id, SUM(debit) - SUM(credit) as balance')
                ->havingRaw('SUM(debit) - SUM(credit) > 0.004')
                ->get()
                ->sum('balance');

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
        ]);
    }
}
