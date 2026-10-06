<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Price;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the unit price for a customer x product on a date.
 *
 * Priority (first match wins):
 *   1. customer + product
 *   2. customer default (customer, no product)
 *   3. group + product
 *   4. group default (group, no product)
 *   5. dated product price (product only)
 *   6. products.default_price
 *
 * Within each level the row with the latest effective_from <= date wins.
 */
class PriceResolver
{
    public function resolve(Customer $customer, Product $product, Carbon|string|null $date = null): string
    {
        $date = $this->normalizeDate($date);

        $rows = $this->candidateRows($customer, collect([$product->id]), $date);

        return $this->pick($rows, $customer, $product) ?? (string) $product->default_price;
    }

    /**
     * Resolve prices for many products at once (used by the order grid).
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, string> product_id => unit price
     */
    public function resolveMany(Customer $customer, Collection $products, Carbon|string|null $date = null): array
    {
        $date = $this->normalizeDate($date);

        $rows = $this->candidateRows($customer, $products->pluck('id'), $date);

        $result = [];
        foreach ($products as $product) {
            $result[$product->id] = $this->pick($rows, $customer, $product) ?? (string) $product->default_price;
        }

        return $result;
    }

    private function normalizeDate(Carbon|string|null $date): Carbon
    {
        return $date instanceof Carbon ? $date : Carbon::parse($date ?? today());
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, Price>
     */
    private function candidateRows(Customer $customer, Collection $productIds, Carbon $date): Collection
    {
        return Price::query()
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($customer, $productIds) {
                $q->where(function (Builder $q) use ($customer, $productIds) {
                    $q->where('customer_id', $customer->id)
                        ->where(fn (Builder $q) => $q->whereIn('product_id', $productIds)->orWhereNull('product_id'));
                });

                if ($customer->customer_group_id !== null) {
                    $q->orWhere(function (Builder $q) use ($customer, $productIds) {
                        $q->where('customer_group_id', $customer->customer_group_id)
                            ->where(fn (Builder $q) => $q->whereIn('product_id', $productIds)->orWhereNull('product_id'));
                    });
                }

                $q->orWhere(function (Builder $q) use ($productIds) {
                    $q->whereNull('customer_id')
                        ->whereNull('customer_group_id')
                        ->whereIn('product_id', $productIds);
                });
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Price>  $rows  ordered by effective_from desc
     */
    private function pick(Collection $rows, Customer $customer, Product $product): ?string
    {
        $levels = [
            fn (Price $p) => $p->customer_id === $customer->id && $p->product_id === $product->id,
            fn (Price $p) => $p->customer_id === $customer->id && $p->product_id === null,
            fn (Price $p) => $p->customer_group_id !== null
                && $p->customer_group_id === $customer->customer_group_id
                && $p->product_id === $product->id,
            fn (Price $p) => $p->customer_group_id !== null
                && $p->customer_group_id === $customer->customer_group_id
                && $p->product_id === null,
            fn (Price $p) => $p->customer_id === null
                && $p->customer_group_id === null
                && $p->product_id === $product->id,
        ];

        foreach ($levels as $matches) {
            $row = $rows->first($matches);
            if ($row !== null) {
                return (string) $row->price;
            }
        }

        return null;
    }
}
