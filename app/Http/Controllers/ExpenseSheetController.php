<?php

namespace App\Http\Controllers;

use App\Exports\TemplateExport;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseSheetController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        [$month, $categories, $cells, $notes] = $this->sheetData($request);

        return Inertia::render('expenses/sheet', [
            'month' => $month->format('Y-m'),
            'daysInMonth' => $month->daysInMonth,
            'categories' => $categories,
            'cells' => (object) $cells,
            'notes' => (object) $notes,
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name']),
            'drivers' => User::role('driver')->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        [$month, $categories, $cells] = $this->sheetData($request);

        $headings = [__('common.date'), ...array_column($categories, 'name_ar'), __('common.total')];
        $rows = [];

        foreach (range(1, $month->daysInMonth) as $day) {
            $row = [$month->format('Y-m-').str_pad((string) $day, 2, '0', STR_PAD_LEFT)];
            $total = 0.0;
            foreach ($categories as $category) {
                $value = (float) ($cells["{$day}:{$category['id']}"] ?? 0);
                $row[] = $value > 0 ? $value : '';
                $total += $value;
            }
            $row[] = $total > 0 ? $total : '';
            $rows[] = $row;
        }

        return Excel::download(
            new TemplateExport($headings, $rows),
            "laheeb-expenses-{$month->format('Y-m')}.xlsx",
        );
    }

    private function sheetData(Request $request): array
    {
        $month = Carbon::parse(($request->input('month', today()->format('Y-m'))).'-01');

        $categories = ExpenseCategory::where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name_ar', 'kind'])
            ->map(fn ($c) => ['id' => $c->id, 'name_ar' => $c->name_ar, 'kind' => $c->kind])
            ->all();

        $expenses = Expense::query()
            ->whereDate('expense_date', '>=', $month->copy()->startOfMonth())
            ->whereDate('expense_date', '<=', $month->copy()->endOfMonth())
            ->where('status', '!=', 'void')
            ->get(['id', 'expense_date', 'expense_category_id', 'amount', 'note']);

        $cells = [];
        $notes = [];
        foreach ($expenses as $expense) {
            $key = $expense->expense_date->day.':'.$expense->expense_category_id;
            $cells[$key] = number_format((float) ($cells[$key] ?? 0) + (float) $expense->amount, 2, '.', '');
            if ($expense->note) {
                $dayKey = (string) $expense->expense_date->day;
                $notes[$dayKey] = trim(($notes[$dayKey] ?? '').' '.$expense->note);
            }
        }

        return [$month, $categories, $cells, $notes];
    }
}
