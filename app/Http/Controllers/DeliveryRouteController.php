<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryRouteRequest;
use App\Models\DeliveryRoute;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRouteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DeliveryRoute::class);

        $routes = DeliveryRoute::query()
            ->with('defaultDriver:id,name')
            ->withCount('customers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DeliveryRoute $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'city' => $r->city,
                'default_driver' => $r->defaultDriver?->only(['id', 'name']),
                'customers_count' => $r->customers_count,
                'active' => $r->active,
                'sort_order' => $r->sort_order,
            ]);

        return Inertia::render('routes/index', [
            'routes' => $routes,
            'drivers' => User::role('driver')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('routes.manage'),
        ]);
    }

    public function store(DeliveryRouteRequest $request): RedirectResponse
    {
        $this->authorize('create', DeliveryRoute::class);

        DeliveryRoute::create($request->validated());

        return redirect()->route('delivery-routes.index')->with('success', __('common.saved'));
    }

    public function update(DeliveryRouteRequest $request, DeliveryRoute $deliveryRoute): RedirectResponse
    {
        $this->authorize('update', $deliveryRoute);

        $deliveryRoute->update($request->validated());

        return redirect()->route('delivery-routes.index')->with('success', __('common.saved'));
    }

    public function destroy(DeliveryRoute $deliveryRoute): RedirectResponse
    {
        $this->authorize('delete', $deliveryRoute);

        $deliveryRoute->delete();

        return redirect()->route('delivery-routes.index')->with('success', __('common.deleted'));
    }
}
