<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use App\Models\LedgerEntry;
use App\Models\Price;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'route_id' => $request->input('route_id'),
            'group_id' => $request->input('group_id'),
            'type' => $request->input('type'),
            'payment_term' => $request->input('payment_term'),
            'status' => $request->input('status'),
        ];

        $customers = Customer::query()
            ->with(['group:id,name', 'route:id,name'])
            ->search($filters['search'])
            ->when($filters['route_id'], fn ($q, $v) => $q->where('delivery_route_id', $v))
            ->when($filters['group_id'], fn ($q, $v) => $q->where('customer_group_id', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($filters['payment_term'], fn ($q, $v) => $q->where('payment_term', $v))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $canViewPrices = $request->user()->can('prices.view');
        $defaultPrices = collect();

        if ($canViewPrices) {
            $ids = collect($customers->items())->pluck('id');
            $defaultPrices = Price::query()
                ->whereIn('customer_id', $ids)
                ->whereNull('product_id')
                ->whereDate('effective_from', '<=', today())
                ->orderBy('effective_from')
                ->orderBy('id')
                ->get()
                ->groupBy('customer_id')
                ->map(fn ($rows) => (string) $rows->last()->price);
        }

        $customers->through(fn (Customer $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'group' => $c->group?->only(['id', 'name']),
            'route' => $c->route?->only(['id', 'name']),
            'type' => $c->type,
            'payment_term' => $c->payment_term,
            'phone' => $c->phone,
            'city' => $c->city,
            'active' => $c->active,
            'default_price' => $canViewPrices ? ($defaultPrices[$c->id] ?? null) : null,
        ]);

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => $filters,
            'routes' => DeliveryRoute::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'groups' => CustomerGroup::orderBy('name')->get(['id', 'name']),
            'canViewPrices' => $canViewPrices,
            'canManage' => $request->user()->can('customers.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('customers/form', [
            'customer' => null,
            'prices' => [],
            'suggestedCode' => Customer::nextCode(),
            'groups' => CustomerGroup::orderBy('name')->get(['id', 'name']),
            'routes' => DeliveryRoute::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'name_en']),
            'canManagePrices' => $request->user()->can('prices.manage'),
            'canViewPrices' => $request->user()->can('prices.view'),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validated();
        $defaultPrice = Arr::pull($data, 'default_price');

        if (empty($data['code'])) {
            $data['code'] = Customer::nextCode();
        }

        DB::transaction(function () use ($data, $defaultPrice, $request) {
            $customer = Customer::create($data);

            if ($defaultPrice !== null && $defaultPrice !== '' && $request->user()->can('prices.manage')) {
                Price::create([
                    'customer_id' => $customer->id,
                    'price' => $defaultPrice,
                    'effective_from' => today(),
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('customers.index')->with('success', __('common.saved'));
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $canViewPrices = $request->user()->can('prices.view');

        return Inertia::render('customers/form', [
            'customer' => $customer->only([
                'id', 'code', 'name', 'name_en', 'customer_group_id', 'type', 'payment_term',
                'credit_limit', 'credit_days', 'delivery_route_id', 'stop_sequence', 'city',
                'phone', 'whatsapp', 'map_url', 'vat_number', 'cr_number', 'national_address',
                'active', 'notes',
            ]),
            'prices' => $canViewPrices
                ? $customer->prices()
                    ->with('product:id,name_ar,name_en')
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (Price $p) => [
                        'id' => $p->id,
                        'product' => $p->product?->only(['id', 'name_ar', 'name_en']),
                        'price' => (string) $p->price,
                        'effective_from' => $p->effective_from->toDateString(),
                    ])
                : [],
            'suggestedCode' => null,
            'groups' => CustomerGroup::orderBy('name')->get(['id', 'name']),
            'routes' => DeliveryRoute::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'name_en']),
            'canManagePrices' => $request->user()->can('prices.manage'),
            'canViewPrices' => $canViewPrices,
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = Arr::except($request->validated(), ['default_price']);
        $customer->update($data);

        return redirect()->route('customers.index')->with('success', __('common.saved'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        // A customer who still owes (or is owed) money stays visible:
        // deleting them would desync the receivables screens from the ledger.
        $balance = LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id);

        if (! Money::isZero($balance)) {
            return back()->with('error', __('customers.has_balance'));
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('common.deleted'));
    }
}
