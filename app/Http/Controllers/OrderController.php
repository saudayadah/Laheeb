<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRoute;
use App\Services\OrderGridService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private OrderGridService $service) {}

    public function grid(Request $request): Response
    {
        abort_unless($request->user()->can('orders.manage'), 403);

        $date = $this->date($request);
        $grid = $this->service->grid($date);

        return Inertia::render('orders/grid', [
            'date' => $date->toDateString(),
            'weekday' => $date->dayOfWeek,
            'products' => $grid['products'],
            'routeGroups' => $grid['routeGroups'],
            'cells' => (object) $grid['cells'],
            'confirmedCustomerIds' => $grid['confirmedCustomerIds'],
            'canViewPrices' => $request->user()->can('prices.view'),
        ]);
    }

    /** Lightweight JSON autosave used by the grid (single cells, paste blocks). */
    public function saveCells(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('orders.manage'), 403);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'cells' => ['required', 'array', 'max:500'],
            'cells.*.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'cells.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'cells.*.qty' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $this->service->saveCells(Carbon::parse($validated['date']), $validated['cells'], $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function fill(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('orders.manage'), 403);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'source' => ['required', 'in:yesterday,last_week,standing'],
        ]);

        $count = $this->service->fill(Carbon::parse($validated['date']), $validated['source'], $request->user()->id);

        return back()->with('success', __('orders.filled', ['count' => $count]));
    }

    public function production(Request $request): Response
    {
        abort_unless($request->user()->can('orders.manage'), 403);

        $date = $this->date($request);

        return Inertia::render('orders/production', [
            'date' => $date->toDateString(),
            'weekday' => $date->dayOfWeek,
            'summary' => $this->service->productionSummary($date),
        ]);
    }

    public function loading(Request $request): Response
    {
        abort_unless($request->user()->can('orders.manage'), 403);

        $date = $this->date($request);

        $routes = DeliveryRoute::where('active', true)
            ->with('defaultDriver:id,name')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'default_driver_id']);

        $routeId = (int) $request->input('route_id', $routes->first()?->id ?? 0);
        $route = $routes->firstWhere('id', $routeId);

        return Inertia::render('orders/loading', [
            'date' => $date->toDateString(),
            'weekday' => $date->dayOfWeek,
            'routes' => $routes->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'driver' => $r->defaultDriver?->name,
            ]),
            'routeId' => $route?->id,
            'sheet' => $route ? $this->service->loadingSheet($date, $route->id) : null,
        ]);
    }

    private function date(Request $request): Carbon
    {
        try {
            return Carbon::parse($request->input('date', today()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }
}
