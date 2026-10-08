<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $today = Invoice::query()
            ->whereDate('invoice_date', today())
            ->where('status', Invoice::STATUS_POSTED)
            ->whereIn('source', ['counter', 'daily_retail'])
            ->select('payment_method', DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(total), 0) as sum'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        return Inertia::render('pos/index', [
            'products' => Product::where('active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name_ar', 'default_price'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name_ar' => $p->name_ar,
                    'price' => (string) $p->default_price,
                ]),
            'todayCash' => (string) ($today['cash']->sum ?? '0'),
            'todayMada' => (string) ($today['mada']->sum ?? '0'),
            'todayCount' => (int) $today->sum('cnt'),
        ]);
    }

    public function sale(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'payment_method' => ['required', 'in:cash,mada'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $invoice = $this->invoices->postDirect([
            'source' => 'counter',
            'payment_method' => $validated['payment_method'],
        ], $validated['items'], $request->user(), $validated['idempotency_key']);

        return redirect()
            ->route('invoices.thermal', $invoice)
            ->with('success', __('pos.sold', ['total' => (string) $invoice->total]));
    }

    /** One line for the whole day, for days when individual counter sales are not keyed. */
    public function dailyRetail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'payment_method' => ['required', 'in:cash,mada'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $this->invoices->postDirect([
            'source' => 'daily_retail',
            'payment_method' => $validated['payment_method'],
            'invoice_date' => $validated['date'],
        ], [[
            'product_id' => null,
            'description' => __('pos.daily_retail_line'),
            'qty' => 1,
            'unit_price' => (string) $validated['amount'],
        ]], $request->user(), $validated['idempotency_key']);

        return back()->with('success', __('common.saved'));
    }
}
