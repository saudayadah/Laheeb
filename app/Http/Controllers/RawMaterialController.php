<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RawMaterial;
use App\Models\RawMaterialMovement;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $from = today()->startOfMonth();

        // Loaves actually sold this month — the denominator for consumption/1000.
        $loavesThisMonth = (int) DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.status', Invoice::STATUS_POSTED)
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereNotNull('invoice_lines.product_id')
            ->sum('invoice_lines.qty');

        $materials = RawMaterial::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (RawMaterial $material) use ($from, $loavesThisMonth) {
                $consumedThisMonth = (string) $material->movements()
                    ->where('direction', 'out')
                    ->whereDate('movement_date', '>=', $from)
                    ->sum('qty');

                $onHand = $material->onHand();

                return [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'on_hand' => $onHand,
                    'reorder_level' => $material->reorder_level !== null ? (string) $material->reorder_level : null,
                    'low' => $material->reorder_level !== null && Money::compare($onHand, (string) $material->reorder_level) <= 0,
                    'consumed_month' => $consumedThisMonth,
                    'per_1000_loaves' => $loavesThisMonth > 0
                        ? number_format(((float) $consumedThisMonth / $loavesThisMonth) * 1000, 2, '.', '')
                        : null,
                    'active' => $material->active,
                ];
            });

        $movements = RawMaterialMovement::query()
            ->with('material:id,name,unit')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (RawMaterialMovement $m) => [
                'id' => $m->id,
                'date' => $m->movement_date->toDateString(),
                'material' => $m->material?->only(['id', 'name', 'unit']),
                'direction' => $m->direction,
                'qty' => (string) $m->qty,
                'notes' => $m->notes,
            ]);

        return Inertia::render('materials/index', [
            'materials' => $materials,
            'movements' => $movements,
            'loavesThisMonth' => $loavesThisMonth,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        RawMaterial::create($this->validated($request));

        return back()->with('success', __('common.saved'));
    }

    public function update(Request $request, RawMaterial $material): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $material->update($this->validated($request, $material->id));

        return back()->with('success', __('common.saved'));
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $validated = $request->validate([
            'raw_material_id' => ['required', 'integer', 'exists:raw_materials,id'],
            'movement_date' => ['required', 'date'],
            'direction' => ['required', 'in:in,out'],
            'qty' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        RawMaterialMovement::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('success', __('common.saved'));
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('raw_materials', 'name')->ignore($id)],
            'unit' => ['required', 'string', 'max:30'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
