<?php

namespace App\Http\Controllers;

use App\Models\LedgerEntry;
use App\Models\Supplier;
use App\Services\ExpenseService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        $suppliers = Supplier::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'vat_number' => $s->vat_number,
                'active' => $s->active,
                'payable' => $s->payable(),
                'notes' => $s->notes,
            ]);

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'totalPayable' => (string) $suppliers->sum(fn ($s) => (float) $s['payable']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        Supplier::create($this->validated($request));

        return back()->with('success', __('common.saved'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        $supplier->update($this->validated($request, $supplier->id));

        return back()->with('success', __('common.saved'));
    }

    /** Supplier statement: every purchase and payment, with a running balance. */
    public function show(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        $entries = LedgerEntry::query()
            ->where('account_type', LedgerEntry::SUPPLIER)
            ->where('account_id', $supplier->id)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = '0.00';
        $rows = $entries->map(function (LedgerEntry $entry) use (&$running) {
            // For a supplier, credit = we owe more, debit = we paid.
            $running = Money::add($running, Money::subtract((string) $entry->credit, (string) $entry->debit));

            return [
                'id' => $entry->id,
                'date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'purchase' => (string) $entry->credit,
                'payment' => (string) $entry->debit,
                'balance' => $running,
            ];
        });

        return Inertia::render('suppliers/show', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'payable' => $supplier->payable(),
            ],
            'entries' => $rows,
        ]);
    }

    public function pay(Request $request, Supplier $supplier, ExpenseService $service): RedirectResponse
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', 'in:counter_cash,bank'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $service->paySupplier($supplier, $validated, $request->user());

        return back()->with('success', __('suppliers.paid'));
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('suppliers', 'name')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
        ]);
    }
}
