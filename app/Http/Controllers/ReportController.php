<?php

namespace App\Http\Controllers;

use App\Exports\TemplateExport;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $year = (int) $request->input('year', now()->year);

        try {
            $from = Carbon::parse($request->input('from', today()->startOfMonth()->toDateString()));
            $to = Carbon::parse($request->input('to', today()->toDateString()));
        } catch (\Throwable) {
            [$from, $to] = [today()->startOfMonth(), today()];
        }

        return Inertia::render('reports/index', [
            'year' => $year,
            'monthly' => $this->service->monthly($year),
            'monthlyPrev' => $this->service->monthly($year - 1),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'salesByProduct' => $this->service->salesByProduct($from, $to),
            'salesByCustomer' => $this->service->salesByCustomer($from, $to),
            'salesByRoute' => $this->service->salesByRoute($from, $to),
            'collectionsByDriver' => $this->service->collectionsByDriver($from, $to),
            'custodyByDriver' => $this->service->custodyByDriver(),
            'inactiveDays' => $inactiveDays = max(1, (int) $request->input('inactive_days', 3)),
            'inactiveCustomers' => $this->service->inactiveCustomers($inactiveDays),
            'dropPercent' => $dropPercent = max(5, (int) $request->input('drop_percent', 30)),
            'volumeDrops' => $this->service->volumeDrops($dropPercent),
        ]);
    }

    public function exportMonthly(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $year = (int) $request->input('year', now()->year);
        $rows = $this->service->monthly($year);

        $headings = [
            __('reports.month'), __('reports.total'), __('invoices.method.cash'), __('invoices.method.credit'),
            __('invoices.method.mada'), __('reports.collections'), __('reports.expenses'),
            __('reports.expenses_cash'), __('reports.expenses_bank'), __('reports.salaries'),
            __('reports.rent'), __('reports.net'), __('reports.net_cash'),
        ];

        $data = array_map(fn ($row) => [
            sprintf('%d-%02d', $year, $row['month']),
            $row['total'], $row['cash'], $row['credit'], $row['mada'], $row['collections'],
            $row['expenses_total'], $row['expenses_cash'], $row['expenses_bank'],
            $row['salaries'], $row['rent'], $row['net'], $row['net_cash'],
        ], $rows);

        return Excel::download(new TemplateExport($headings, $data), "laheeb-monthly-{$year}.xlsx");
    }
}
