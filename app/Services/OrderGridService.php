<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Price;
use App\Models\Product;
use App\Models\StandingOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderGridService
{
    /**
     * Everything the daily grid screen needs for one date.
     */
    public function grid(Carbon $date, bool $withPrices = true): array
    {
        $products = Product::where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name_ar', 'name_en', 'size_cm']);

        $customers = Customer::where('active', true)
            ->with('route:id,name,sort_order')
            ->orderBy('stop_sequence')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'payment_term', 'delivery_route_id', 'stop_sequence']);

        $defaultPrices = collect();
        if ($withPrices) {
            $defaultPrices = Price::query()
                ->whereIn('customer_id', $customers->pluck('id'))
                ->whereNull('product_id')
                ->whereDate('effective_from', '<=', $date)
                ->orderBy('effective_from')
                ->orderBy('id')
                ->get()
                ->groupBy('customer_id')
                ->map(fn ($rows) => (string) $rows->last()->price);
        }

        // Group customers by route; routes ordered by sort_order, no-route last.
        $routeGroups = $customers
            ->groupBy(fn (Customer $c) => $c->delivery_route_id ?? 0)
            ->map(function ($group) use ($defaultPrices) {
                $route = $group->first()->route;

                return [
                    'route' => $route ? ['id' => $route->id, 'name' => $route->name, 'sort' => $route->sort_order] : null,
                    'customers' => $group->map(fn (Customer $c) => [
                        'id' => $c->id,
                        'code' => $c->code,
                        'name' => $c->name,
                        'payment_term' => $c->payment_term,
                        'price' => $defaultPrices[$c->id] ?? null,
                    ])->values(),
                ];
            })
            ->sortBy(fn ($g) => $g['route']['sort'] ?? PHP_INT_MAX)
            ->values();

        // Existing quantities and confirmation status for the date.
        $orders = Order::whereDate('order_date', $date)->with('lines')->get();

        $cells = [];
        $confirmed = [];
        foreach ($orders as $order) {
            if ($order->isConfirmed()) {
                $confirmed[] = $order->customer_id;
            }
            foreach ($order->lines as $line) {
                $cells["{$order->customer_id}:{$line->product_id}"] = $line->qty;
            }
        }

        return [
            'products' => $products,
            'routeGroups' => $routeGroups,
            'cells' => $cells,
            'confirmedCustomerIds' => $confirmed,
        ];
    }

    /**
     * Save a batch of cells (autosave, paste, fill). qty 0 clears the cell.
     *
     * @param  array<int, array{customer_id: int, product_id: int, qty: int}>  $cells
     */
    public function saveCells(Carbon $date, array $cells, ?int $userId): void
    {
        DB::transaction(function () use ($date, $cells, $userId) {
            $orders = [];

            foreach ($cells as $cell) {
                $customerId = (int) $cell['customer_id'];

                // Row lock so an autosave landing mid-confirmDay serializes
                // behind it and sees the fresh 'confirmed' status.
                $order = $orders[$customerId] ??= (
                    Order::whereDate('order_date', $date)
                        ->where('customer_id', $customerId)
                        ->lockForUpdate()
                        ->first()
                    ?? Order::create([
                        'order_date' => $date->copy()->startOfDay(),
                        'customer_id' => $customerId,
                        'created_by' => $userId,
                    ])
                );

                if ($order->isConfirmed()) {
                    throw ValidationException::withMessages([
                        'cells' => __('orders.day_confirmed'),
                    ]);
                }

                $qty = (int) $cell['qty'];

                if ($qty <= 0) {
                    OrderLine::where('order_id', $order->id)
                        ->where('product_id', $cell['product_id'])
                        ->delete();
                } else {
                    OrderLine::updateOrCreate(
                        ['order_id' => $order->id, 'product_id' => $cell['product_id']],
                        ['qty' => $qty],
                    );
                }
            }
        });
    }

    /**
     * Fill empty cells from yesterday, the same weekday last week, or standing orders.
     * Never overwrites a quantity that is already entered. Returns filled-cell count.
     */
    public function fill(Carbon $date, string $source, ?int $userId): int
    {
        $incoming = match ($source) {
            'yesterday' => $this->linesOf($date->copy()->subDay()),
            'last_week' => $this->linesOf($date->copy()->subWeek()),
            'standing' => StandingOrder::where('active', true)
                ->where('weekday', $date->dayOfWeek)
                ->get()
                ->map(fn (StandingOrder $s) => [
                    'customer_id' => $s->customer_id,
                    'product_id' => $s->product_id,
                    'qty' => $s->qty,
                ]),
            default => collect(),
        };

        if ($incoming->isEmpty()) {
            return 0;
        }

        $activeCustomerIds = Customer::where('active', true)->pluck('id')->flip();

        $existing = $this->grid($date, withPrices: false)['cells'];

        $toSave = [];
        foreach ($incoming as $cell) {
            $key = "{$cell['customer_id']}:{$cell['product_id']}";
            if (! isset($existing[$key]) && isset($activeCustomerIds[$cell['customer_id']])) {
                $toSave[] = $cell;
            }
        }

        if ($toSave !== []) {
            $this->saveCells($date, $toSave, $userId);
        }

        return count($toSave);
    }

    /**
     * Production totals for the bakers: per product, overall and per route.
     */
    public function productionSummary(Carbon $date): array
    {
        $rows = OrderLine::query()
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('delivery_routes', 'delivery_routes.id', '=', 'customers.delivery_route_id')
            ->whereDate('orders.order_date', $date)
            ->groupBy('order_lines.product_id', 'customers.delivery_route_id', 'delivery_routes.name', 'delivery_routes.sort_order')
            ->selectRaw('order_lines.product_id, customers.delivery_route_id as route_id, delivery_routes.name as route_name, delivery_routes.sort_order as route_sort, SUM(order_lines.qty) as qty')
            ->get();

        $products = Product::where('active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'size_cm']);

        $byProduct = [];
        $routes = [];
        foreach ($rows as $row) {
            $byProduct[$row->product_id] = ($byProduct[$row->product_id] ?? 0) + (int) $row->qty;
            $routeKey = $row->route_id ?? 0;
            $routes[$routeKey]['name'] = $row->route_name;
            $routes[$routeKey]['sort'] = $row->route_sort ?? PHP_INT_MAX;
            $routes[$routeKey]['products'][$row->product_id] = (int) $row->qty;
            $routes[$routeKey]['total'] = ($routes[$routeKey]['total'] ?? 0) + (int) $row->qty;
        }

        uasort($routes, fn ($a, $b) => ($a['sort'] <=> $b['sort']));

        return [
            'products' => $products,
            'totalsByProduct' => $byProduct,
            'routes' => array_values($routes),
            'grandTotal' => array_sum($byProduct),
        ];
    }

    /**
     * Loading sheet for one route: stops in order x products.
     */
    public function loadingSheet(Carbon $date, int $routeId): array
    {
        $products = Product::where('active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'size_cm']);

        $customers = Customer::where('active', true)
            ->where('delivery_route_id', $routeId)
            ->orderBy('stop_sequence')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'stop_sequence']);

        $cells = $this->grid($date, withPrices: false)['cells'];

        $rows = [];
        $totals = [];
        foreach ($customers as $customer) {
            $row = ['customer' => $customer->only(['id', 'code', 'name', 'stop_sequence']), 'qtys' => [], 'total' => 0];
            foreach ($products as $product) {
                $qty = $cells["{$customer->id}:{$product->id}"] ?? 0;
                $row['qtys'][$product->id] = $qty;
                $row['total'] += $qty;
                $totals[$product->id] = ($totals[$product->id] ?? 0) + $qty;
            }
            if ($row['total'] > 0) {
                $rows[] = $row;
            }
        }

        return [
            'products' => $products,
            'rows' => $rows,
            'totals' => $totals,
            'grandTotal' => array_sum($totals),
        ];
    }

    private function linesOf(Carbon $date): Collection
    {
        return OrderLine::query()
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->whereDate('orders.order_date', $date)
            ->get(['orders.customer_id', 'order_lines.product_id', 'order_lines.qty'])
            ->map(fn ($line) => [
                'customer_id' => $line->customer_id,
                'product_id' => $line->product_id,
                'qty' => $line->qty,
            ]);
    }
}
