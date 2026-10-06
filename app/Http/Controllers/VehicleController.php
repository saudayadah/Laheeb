<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        // Running cost per vehicle, from the expenses already recorded against it.
        $costs = Expense::query()
            ->where('status', 'approved')
            ->whereNotNull('vehicle_id')
            ->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, COALESCE(SUM(amount), 0) as total')
            ->pluck('total', 'vehicle_id');

        $latestMaintenance = VehicleMaintenance::query()
            ->whereIn('id', VehicleMaintenance::selectRaw('MAX(id)')->groupBy('vehicle_id'))
            ->get()
            ->keyBy('vehicle_id');

        $vehicles = Vehicle::query()
            ->orderBy('name')
            ->get()
            ->map(function (Vehicle $vehicle) use ($costs, $latestMaintenance) {
                $last = $latestMaintenance->get($vehicle->id);

                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'plate' => $vehicle->plate,
                    'active' => $vehicle->active,
                    'total_cost' => Money::add((string) ($costs[$vehicle->id] ?? '0'), '0.00'),
                    'last_service' => $last?->service_date->toDateString(),
                    'next_due_date' => $last?->next_due_date?->toDateString(),
                    'due_soon' => $last !== null && $last->isDue(),
                ];
            });

        $maintenances = VehicleMaintenance::query()
            ->with('vehicle:id,name,plate')
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (VehicleMaintenance $m) => [
                'id' => $m->id,
                'vehicle' => $m->vehicle?->only(['id', 'name', 'plate']),
                'date' => $m->service_date->toDateString(),
                'task' => $m->task,
                'odometer' => $m->odometer,
                'next_due_date' => $m->next_due_date?->toDateString(),
                'next_due_odometer' => $m->next_due_odometer,
                'due_soon' => $m->isDue(),
                'notes' => $m->notes,
            ]);

        return Inertia::render('vehicles/index', [
            'vehicles' => $vehicles,
            'maintenances' => $maintenances,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        Vehicle::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'plate' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
        ]));

        return back()->with('success', __('common.saved'));
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $vehicle->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'plate' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
        ]));

        return back()->with('success', __('common.saved'));
    }

    public function storeMaintenance(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.manage'), 403);

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'service_date' => ['required', 'date'],
            'task' => ['required', 'string', 'max:150'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'next_due_date' => ['nullable', 'date', 'after:service_date'],
            'next_due_odometer' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        VehicleMaintenance::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('success', __('common.saved'));
    }
}
