<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DailyClose;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Money;
use App\Support\VatMath;
use App\Support\ZatcaQr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private PriceResolver $prices,
        private PostingService $posting,
        private CreditControlService $creditControl,
    ) {}

    /**
     * Price and total a set of items.
     *
     * @param  array<int, array{product_id?: int|null, description?: string|null, qty: int, unit_price?: string|null}>  $items
     * @return array{lines: array, subtotal: string, vat: string, total: string, prices_include_vat: bool}
     */
    public function buildLines(?Customer $customer, array $items, Carbon $date): array
    {
        $vatEnabled = AppSettings::vatEnabled();
        $globalRate = $vatEnabled ? AppSettings::vatRate() : '0';
        $include = AppSettings::pricesIncludeVat();

        $products = Product::whereIn('id', collect($items)->pluck('product_id')->filter())
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = '0.00';
        $vat = '0.00';
        $total = '0.00';

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $product = ! empty($item['product_id']) ? ($products[$item['product_id']] ?? null) : null;

            $price = isset($item['unit_price']) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                ? (string) $item['unit_price']
                : ($product !== null && $customer !== null
                    ? $this->prices->resolve($customer, $product, $date)
                    : (string) ($product?->default_price ?? '0'));

            $rate = $vatEnabled ? (string) ($product?->vat_rate ?? $globalRate) : '0';
            $math = VatMath::line($qty, $price, $rate, $include);

            $lines[] = [
                'product_id' => $product?->id,
                'description' => $item['description'] ?? null,
                'qty' => $qty,
                'unit_price' => $price,
                'vat_rate' => $rate,
                'line_subtotal' => $math['subtotal'],
                'line_vat' => $math['vat'],
                'line_total' => $math['total'],
            ];

            $subtotal = Money::add($subtotal, $math['subtotal']);
            $vat = Money::add($vat, $math['vat']);
            $total = Money::add($total, $math['total']);
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
            'prices_include_vat' => $include,
        ];
    }

    /** One draft invoice per customer per day, created from the confirmed order. */
    public function createDraftFromOrder(Order $order, ?int $userId = null): ?Invoice
    {
        $order->loadMissing(['lines', 'customer.route']);

        if ($order->lines->isEmpty() || $order->customer === null) {
            return null;
        }

        if (Invoice::where('order_id', $order->id)->whereIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_POSTED])->exists()) {
            return null;
        }

        $customer = $order->customer;
        $date = Carbon::parse($order->order_date);

        $built = $this->buildLines(
            $customer,
            $order->lines->map(fn ($line) => ['product_id' => $line->product_id, 'qty' => $line->qty])->all(),
            $date,
        );

        $invoice = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'invoice_date' => $date,
            'type' => $customer->vat_number ? 'tax' : 'simplified',
            'source' => 'delivery',
            'payment_method' => $customer->payment_term,
            'status' => Invoice::STATUS_DRAFT,
            'subtotal' => $built['subtotal'],
            'vat_amount' => $built['vat'],
            'total' => $built['total'],
            'prices_include_vat' => $built['prices_include_vat'],
            'driver_id' => $customer->route?->default_driver_id,
            'posted_by' => null,
        ]);

        $invoice->lines()->createMany($built['lines']);

        return $invoice;
    }

    /** Confirm all draft orders of a day: lock them and create their draft invoices. */
    public function confirmDay(Carbon $date, User $user): int
    {
        return DB::transaction(function () use ($date, $user) {
            $orders = Order::whereDate('order_date', $date)
                ->where('status', Order::STATUS_DRAFT)
                ->whereHas('lines')
                ->with(['lines', 'customer.route'])
                ->get();

            $count = 0;
            foreach ($orders as $order) {
                if ($this->createDraftFromOrder($order, $user->id) !== null) {
                    $count++;
                }
                $order->update(['status' => Order::STATUS_CONFIRMED]);
            }

            activity()->causedBy($user)->withProperties(['date' => $date->toDateString(), 'count' => $count])->log('orders.day_confirmed');

            return $count;
        });
    }

    /** Replace the lines of a draft invoice (driver adjusting delivered quantities). */
    public function updateDraftLines(Invoice $invoice, array $items): Invoice
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => __('invoices.immutable')]);
        }

        $built = $this->buildLines($invoice->customer, $items, Carbon::parse($invoice->invoice_date));

        $invoice->lines()->delete();
        $invoice->lines()->createMany($built['lines']);
        $invoice->update([
            'subtotal' => $built['subtotal'],
            'vat_amount' => $built['vat'],
            'total' => $built['total'],
            'prices_include_vat' => $built['prices_include_vat'],
        ]);

        return $invoice->refresh();
    }

    /** Post a draft invoice: gapless number, hash chain, QR, ledger. Idempotent. */
    public function post(
        Invoice $invoice,
        User $user,
        ?string $idempotencyKey = null,
        bool $overrideCredit = false,
        ?string $overrideReason = null,
    ): Invoice {
        return DB::transaction(function () use ($invoice, $user, $idempotencyKey, $overrideCredit, $overrideReason) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->isPosted()) {
                return $invoice; // retry on a weak connection: already done
            }

            if ($invoice->status === Invoice::STATUS_VOID) {
                throw ValidationException::withMessages(['invoice' => __('invoices.is_void')]);
            }

            if (Money::isZero((string) $invoice->total)) {
                throw ValidationException::withMessages(['invoice' => __('invoices.empty')]);
            }

            // An approved daily close locks that driver's day.
            if ($invoice->driver_id !== null) {
                $locked = DailyClose::where('closeable_type', DailyClose::TYPE_DRIVER)
                    ->where('closeable_id', $invoice->driver_id)
                    ->whereDate('close_date', $invoice->invoice_date)
                    ->where('status', 'approved')
                    ->exists();

                if ($locked) {
                    throw ValidationException::withMessages(['invoice' => __('closes.day_locked')]);
                }
            }

            if ($invoice->payment_method === 'credit' && $invoice->customer !== null) {
                $blocked = $this->creditControl->check($invoice->customer, (string) $invoice->total);

                if ($blocked !== null && ! $overrideCredit) {
                    throw ValidationException::withMessages(['credit' => __($blocked)]);
                }

                if ($blocked !== null && $overrideCredit) {
                    activity()
                        ->causedBy($user)
                        ->performedOn($invoice)
                        ->withProperties(['reason' => $overrideReason, 'check' => $blocked])
                        ->log('invoice.credit_override');
                }
            }

            $number = DocumentSequence::allocate($invoice->series);
            $prevHash = Invoice::where('series', $invoice->series)
                ->where('status', Invoice::STATUS_POSTED)
                ->whereNotNull('hash')
                ->orderByDesc('number')
                ->value('hash');

            $hash = hash('sha256', implode('|', [
                $invoice->uuid,
                $invoice->series.'-'.$number,
                $invoice->invoice_date->toDateString(),
                (string) $invoice->total,
                (string) $invoice->vat_amount,
                $prevHash ?? '',
            ]));

            $invoice->forceFill([
                'number' => $number,
                'status' => Invoice::STATUS_POSTED,
                'hash' => $hash,
                'prev_hash' => $prevHash,
                'qr_payload' => ZatcaQr::payload(
                    (string) AppSettings::get('bakery_name_ar'),
                    (string) AppSettings::get('vat_number'),
                    now(),
                    (string) $invoice->total,
                    (string) $invoice->vat_amount,
                ),
                'idempotency_key' => $idempotencyKey ?? $invoice->idempotency_key,
                'posted_at' => now(),
                'posted_by' => $user->id,
            ])->save();

            $this->posting->postInvoice($invoice);

            activity()->causedBy($user)->performedOn($invoice)->log('invoice.posted');

            return $invoice;
        });
    }

    /** Create and post in one step (counter sale, van sale, daily retail total). */
    public function postDirect(array $attrs, array $items, User $user, ?string $idempotencyKey = null): Invoice
    {
        if ($idempotencyKey !== null) {
            $existing = Invoice::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($attrs, $items, $user, $idempotencyKey) {
            $customer = isset($attrs['customer_id']) && $attrs['customer_id']
                ? Customer::findOrFail($attrs['customer_id'])
                : null;

            $date = Carbon::parse($attrs['invoice_date'] ?? today());
            $built = $this->buildLines($customer, $items, $date);

            $invoice = Invoice::create([
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer?->id,
                'order_id' => $attrs['order_id'] ?? null,
                'invoice_date' => $date,
                'type' => $customer?->vat_number ? 'tax' : 'simplified',
                'source' => $attrs['source'] ?? 'counter',
                'payment_method' => $attrs['payment_method'] ?? 'cash',
                'status' => Invoice::STATUS_DRAFT,
                'subtotal' => $built['subtotal'],
                'vat_amount' => $built['vat'],
                'total' => $built['total'],
                'prices_include_vat' => $built['prices_include_vat'],
                'driver_id' => $attrs['driver_id'] ?? null,
                'notes' => $attrs['notes'] ?? null,
            ]);

            $invoice->lines()->createMany($built['lines']);

            return $this->post(
                $invoice,
                $user,
                $idempotencyKey,
                (bool) ($attrs['override_credit'] ?? false),
                $attrs['override_reason'] ?? null,
            );
        });
    }

    public function void(Invoice $invoice, User $user, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $user, $reason) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->isPosted()) {
                throw ValidationException::withMessages(['invoice' => __('invoices.not_posted')]);
            }

            $invoice->forceFill([
                'status' => Invoice::STATUS_VOID,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
            ])->save();

            $this->posting->reverseInvoice($invoice, $reason);

            activity()->causedBy($user)->performedOn($invoice)->withProperties(['reason' => $reason])->log('invoice.voided');

            return $invoice;
        });
    }

    /** Cash <-> credit (or mada) conversion on a posted invoice, fully logged. */
    public function reclassify(Invoice $invoice, string $newMethod, User $user, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $newMethod, $user, $reason) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->isPosted()) {
                throw ValidationException::withMessages(['invoice' => __('invoices.not_posted')]);
            }

            if ($invoice->payment_method === $newMethod) {
                return $invoice;
            }

            $old = $invoice->payment_method;

            $invoice->forceFill([
                'payment_method' => $newMethod,
                'reclass_reason' => $reason,
                'reclassified_at' => now(),
                'reclassified_by' => $user->id,
            ])->save();

            $this->posting->repostInvoice($invoice, $reason);

            activity()
                ->causedBy($user)
                ->performedOn($invoice)
                ->withProperties(['from' => $old, 'to' => $newMethod, 'reason' => $reason])
                ->log('invoice.reclassified');

            return $invoice;
        });
    }

    /**
     * Credit note (returns / corrections), posted immediately.
     *
     * @param  array<int, array{product_id?: int|null, qty: int, condition?: string, unit_price?: string|null}>  $items
     */
    public function createCreditNote(
        ?Invoice $invoice,
        ?Customer $customer,
        array $items,
        string $reason,
        User $user,
        ?Carbon $date = null,
        ?string $idempotencyKey = null,
    ): CreditNote {
        if ($idempotencyKey !== null) {
            $existing = CreditNote::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($invoice, $customer, $items, $reason, $user, $date, $idempotencyKey) {
            $customer ??= $invoice?->customer;
            $date ??= today();

            // Keep indexes aligned with the built lines (buildLines skips zero qty).
            $items = array_values(array_filter($items, fn ($item) => (int) ($item['qty'] ?? 0) > 0));

            // Default each return line to the price it was invoiced at.
            $invoicePrices = $invoice?->lines->keyBy('product_id');
            foreach ($items as &$item) {
                if (empty($item['unit_price']) && ! empty($item['product_id']) && $invoicePrices?->has($item['product_id'])) {
                    $item['unit_price'] = (string) $invoicePrices[$item['product_id']]->unit_price;
                }
            }
            unset($item);

            $built = $this->buildLines($customer, $items, Carbon::parse($date));

            if (Money::isZero($built['total'])) {
                throw ValidationException::withMessages(['items' => __('invoices.empty')]);
            }

            // A return can never credit more than the invoice still carries.
            if ($invoice !== null) {
                $alreadyCredited = (string) CreditNote::where('invoice_id', $invoice->id)
                    ->where('status', 'posted')
                    ->sum('total');

                $remaining = Money::subtract((string) $invoice->total, $alreadyCredited);

                if (Money::compare($built['total'], $remaining) > 0) {
                    throw ValidationException::withMessages(['items' => __('invoices.credit_note_exceeds')]);
                }
            }

            $note = CreditNote::create([
                'series' => 'CRN',
                'number' => DocumentSequence::allocate('CRN'),
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer?->id,
                'invoice_id' => $invoice?->id,
                'note_date' => $date,
                'reason' => $reason,
                'status' => 'posted',
                'subtotal' => $built['subtotal'],
                'vat_amount' => $built['vat'],
                'total' => $built['total'],
                'idempotency_key' => $idempotencyKey,
                'created_by' => $user->id,
            ]);

            $note->lines()->createMany(array_map(fn ($line, $i) => [
                ...$line,
                'condition' => $items[$i]['condition'] ?? 'good',
            ], $built['lines'], array_keys($built['lines'])));

            $this->posting->postCreditNote($note);

            activity()->causedBy($user)->performedOn($note)->withProperties(['reason' => $reason])->log('credit_note.posted');

            return $note;
        });
    }
}
