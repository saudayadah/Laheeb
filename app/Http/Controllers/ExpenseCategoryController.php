<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        return Inertia::render('expenses/categories', [
            'categories' => ExpenseCategory::orderBy('sort_order')->get(),
            'recurring' => RecurringExpense::with(['category:id,name_ar', 'supplier:id,name'])
                ->orderBy('day_of_month')
                ->get()
                ->map(fn (RecurringExpense $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'category' => $r->category?->only(['id', 'name_ar']),
                    'amount' => (string) $r->amount,
                    'day_of_month' => $r->day_of_month,
                    'paid_from' => $r->paid_from,
                    'supplier' => $r->supplier?->only(['id', 'name']),
                    'active' => $r->active,
                ]),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        ExpenseCategory::create($this->validated($request));

        return back()->with('success', __('common.saved'));
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $category->update($this->validated($request, $category->id));

        return back()->with('success', __('common.saved'));
    }

    public function storeRecurring(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        RecurringExpense::create($this->validatedRecurring($request));

        return back()->with('success', __('common.saved'));
    }

    public function updateRecurring(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $recurring->update($this->validatedRecurring($request));

        return back()->with('success', __('common.saved'));
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name_ar')->ignore($id)],
            'name_en' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', 'in:operating,fixed'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function validatedRecurring(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
            'paid_from' => ['required', 'in:counter_cash,bank,supplier_credit'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'active' => ['boolean'],
        ]);
    }
}
