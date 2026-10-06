<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $filters = [
            'from' => $request->input('from', today()->startOfMonth()->toDateString()),
            'to' => $request->input('to', today()->toDateString()),
            'category_id' => $request->input('category_id'),
            'paid_from' => $request->input('paid_from'),
            'status' => $request->input('status'),
        ];

        $base = Expense::query()
            ->whereDate('expense_date', '>=', $filters['from'])
            ->whereDate('expense_date', '<=', $filters['to'])
            ->when($filters['category_id'], fn ($q, $v) => $q->where('expense_category_id', $v))
            ->when($filters['paid_from'], fn ($q, $v) => $q->where('paid_from', $v));

        $summaryRows = (clone $base)
            ->where('status', 'approved')
            ->select('paid_from', DB::raw('COALESCE(SUM(amount), 0) as sum'))
            ->groupBy('paid_from')
            ->pluck('sum', 'paid_from');

        $pendingCount = (clone $base)->where('status', 'pending')->count();

        $expenses = (clone $base)
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->with(['category:id,name_ar', 'supplier:id,name', 'payer:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Expense $e) => [
                'id' => $e->id,
                'date' => $e->expense_date->toDateString(),
                'category' => $e->category?->name_ar,
                'amount' => (string) $e->amount,
                'paid_from' => $e->paid_from,
                'payer' => $e->payer?->name,
                'supplier' => $e->supplier?->name,
                'status' => $e->status,
                'note' => $e->note,
                'has_photo' => $e->receipt_photo !== null,
            ]);

        $cashTotal = (string) (((float) ($summaryRows['driver_cash'] ?? 0)) + ((float) ($summaryRows['counter_cash'] ?? 0)));

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'summary' => [
                'total' => (string) $summaryRows->sum(fn ($v) => (float) $v),
                'cash' => $cashTotal,
                'bank' => (string) ($summaryRows['bank'] ?? '0'),
                'supplier_credit' => (string) ($summaryRows['supplier_credit'] ?? '0'),
                'pending' => $pendingCount,
            ],
            'categories' => ExpenseCategory::where('active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'kind']),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::where('active', true)->orderBy('name')->get(['id', 'name']),
            'drivers' => User::role('driver')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'canApprove' => $request->user()->can('expenses.approve'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_from' => ['required', 'in:driver_cash,counter_cash,bank,supplier_credit'],
            'paid_by' => ['nullable', 'integer', 'exists:users,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'from_sheet' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->service->create($validated, $request->user(), $request->file('photo'));

        return back()->with('success', __('common.saved'));
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.approve'), 403);

        $this->service->approve($expense, $request->user());

        return back()->with('success', __('expenses.approved'));
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.approve'), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->service->void($expense, $request->user(), $validated['reason']);

        return back()->with('success', __('expenses.voided'));
    }

    /** Receipt photos live on the private disk; serve them through auth. */
    public function photo(Request $request, Expense $expense): StreamedResponse
    {
        abort_unless(
            $request->user()->can('expenses.manage') || $expense->created_by === $request->user()->id,
            403,
        );

        abort_if($expense->receipt_photo === null || ! Storage::exists($expense->receipt_photo), 404);

        return Storage::response($expense->receipt_photo);
    }
}
