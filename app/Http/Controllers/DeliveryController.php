<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\PriceResolver;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function __construct(
        private InvoiceService $invoices,
        private PriceResolver $prices,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('deliveries.own'), 403);

        $driverId = $request->user()->id;

        $customers = Customer::query()
            ->where('active', true)
            ->whereHas('route', fn ($q) => $q->where('default_driver_id', $driverId))
            ->with('route:id,name,sort_order')
            ->orderBy('stop_sequence')
            ->get(['id', 'code', 'name', 'payment_term', 'delivery_route_id', 'stop_sequence', 'phone']);

        $invoices = Invoice::query()
            ->whereDate('invoice_date', today())
            ->whereIn('customer_id', $customers->pluck('id'))
            ->whereIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_POSTED])
            ->get(['id', 'customer_id', 'status', 'total', 'payment_method'])
            ->keyBy('customer_id');

        $stops = $customers
            ->groupBy(fn (Customer $c) => $c->route?->sort_order ?? 0)
            ->sortKeys()
            ->flatMap(fn ($group) => $group)
            ->map(function (Customer $c) use ($invoices) {
                $invoice = $invoices->get($c->id);

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'route' => $c->route?->name,
                    'payment_term' => $c->payment_term,
                    'phone' => $c->phone,
                    'invoice' => $invoice ? [
                        'id' => $invoice->id,
                        'status' => $invoice->status,
                        'total' => (string) $invoice->total,
                        'payment_method' => $invoice->payment_method,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('delivery/index', [
            'stops' => $stops,
            'date' => today()->toDateString(),
            'doneCount' => $stops->filter(fn ($s) => ($s['invoice']['status'] ?? null) === Invoice::STATUS_POSTED)->count(),
            'expenseCategories' => ExpenseCategory::where('active', true)
                ->where('kind', 'operating')
                ->orderBy('sort_order')
                ->get(['id', 'name_ar']),
        ]);
    }

    public function stop(Request $request, Customer $customer): Response
    {
        abort_unless($request->user()->can('deliveries.own'), 403);
        $this->assertOnMyRoute($request, $customer);

        $products = Product::where('active', true)->orderBy('sort_order')->get(['id', 'name_ar']);

        $invoice = Invoice::query()
            ->whereDate('invoice_date', today())
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_POSTED])
            ->with('lines')
            ->first();

        // Proposed quantities: the draft invoice, or today's order if not invoiced yet.
        $qtys = [];
        if ($invoice !== null) {
            foreach ($invoice->lines as $line) {
                if ($line->product_id !== null) {
                    $qtys[$line->product_id] = $line->qty;
                }
            }
        } else {
            $order = Order::whereDate('order_date', today())->where('customer_id', $customer->id)->with('lines')->first();
            foreach ($order?->lines ?? [] as $line) {
                $qtys[$line->product_id] = $line->qty;
            }
        }

        $unitPrices = $this->prices->resolveMany($customer, $products->collect());

        return Inertia::render('delivery/stop', [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'payment_term' => $customer->payment_term,
                'balance' => LedgerEntry::balance(LedgerEntry::CUSTOMER, $customer->id),
            ],
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'unit_price' => $unitPrices[$p->id],
            ]),
            'qtys' => (object) $qtys,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
                'total' => (string) $invoice->total,
            ] : null,
            'date' => today()->toDateString(),
        ]);
    }

    public function postStop(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->can('deliveries.own'), 403);
        $this->assertOnMyRoute($request, $customer);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'returns' => ['array'],
            'returns.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'returns.*.qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'returns.*.condition' => ['required', 'in:good,damaged'],
            'payment_method' => ['required', 'in:cash,mada,credit'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();

        // Idempotent retry: the same key returns the same invoice.
        $existing = Invoice::where('idempotency_key', $validated['idempotency_key'])->first();

        if ($existing === null) {
            $draft = Invoice::query()
                ->whereDate('invoice_date', today())
                ->where('customer_id', $customer->id)
                ->where('status', Invoice::STATUS_DRAFT)
                ->first();

            if ($draft !== null) {
                $this->invoices->updateDraftLines($draft, $validated['items']);
                $draft->update(['payment_method' => $validated['payment_method'], 'driver_id' => $user->id]);
                $invoice = $this->invoices->post($draft, $user, $validated['idempotency_key']);
            } else {
                $invoice = $this->invoices->postDirect([
                    'customer_id' => $customer->id,
                    'source' => 'van',
                    'payment_method' => $validated['payment_method'],
                    'driver_id' => $user->id,
                ], $validated['items'], $user, $validated['idempotency_key']);
            }
        } else {
            $invoice = $existing;
        }

        $returns = array_values(array_filter($validated['returns'] ?? [], fn ($r) => (int) $r['qty'] > 0));
        if ($returns !== [] && $existing === null) {
            $this->invoices->createCreditNote(
                $invoice,
                $customer,
                $returns,
                __('invoices.return_reason'),
                $user,
                idempotencyKey: $validated['idempotency_key'].':ret',
            );
        }

        return redirect()
            ->route('invoices.thermal', $invoice)
            ->with('success', __('delivery.posted', ['total' => (string) $invoice->total]));
    }

    /** Driver collects a cash payment on old credit at the door. */
    public function collect(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->can('deliveries.own'), 403);
        $this->assertOnMyRoute($request, $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $receipt = app(ReceiptService::class)->create(
            [
                'customer_id' => $customer->id,
                'amount' => $validated['amount'],
                'receipt_date' => today()->toDateString(),
                'method' => 'cash',
                'received_by' => $request->user()->id,
            ],
            null,
            $request->user(),
            $validated['idempotency_key'],
        );

        return redirect()
            ->route('receipts.voucher', $receipt)
            ->with('success', __('receipts.created', ['number' => $receipt->displayNumber()]));
    }

    /** Driver records an expense paid from his route cash (with a photo). */
    public function storeExpense(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('deliveries.own'), 403);

        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        app(ExpenseService::class)->create([
            'expense_date' => today()->toDateString(),
            'expense_category_id' => $validated['expense_category_id'],
            'amount' => $validated['amount'],
            'paid_from' => 'driver_cash',
            'paid_by' => $request->user()->id,
            'note' => $validated['note'] ?? null,
        ], $request->user(), $request->file('photo'));

        return redirect()->route('delivery.index')->with('success', __('expenses.submitted'));
    }

    private function assertOnMyRoute(Request $request, Customer $customer): void
    {
        $isMine = $customer->route?->default_driver_id === $request->user()->id;

        abort_unless($isMine || $request->user()->can('invoices.manage'), 403);
    }
}
