<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerGroupRequest;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $groups = CustomerGroup::query()
            ->withCount('customers')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'credit_limit' => $g->credit_limit !== null ? (string) $g->credit_limit : null,
                'credit_scope' => $g->credit_scope,
                'customers_count' => $g->customers_count,
                'active' => $g->active,
                'notes' => $g->notes,
            ]);

        return Inertia::render('groups/index', [
            'groups' => $groups,
            'canManage' => $request->user()->can('customers.manage'),
        ]);
    }

    public function store(CustomerGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', CustomerGroup::class);

        CustomerGroup::create($request->validated());

        return redirect()->route('groups.index')->with('success', __('common.saved'));
    }

    public function update(CustomerGroupRequest $request, CustomerGroup $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $group->update($request->validated());

        return redirect()->route('groups.index')->with('success', __('common.saved'));
    }

    public function destroy(CustomerGroup $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return redirect()->route('groups.index')->with('success', __('common.deleted'));
    }
}
