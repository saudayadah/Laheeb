<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $filters = [
            'from' => $request->input('from', today()->toDateString()),
            'to' => $request->input('to', today()->toDateString()),
            'search' => $request->string('search')->toString(),
            'status' => $request->input('status'),
            'payment_method' => $request->input('payment_method'),
            'source' => $request->input('source'),
        ];

        $base = Invoice::query()
            ->whereDate('invoice_date', '>=', $filters['from'])
            ->whereDate('invoice_date', '<=', $filters['to'])
            ->when($filters['search'], fn ($q, $s) => $q->whereHas(
                'customer',
                fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"),
            ))
            ->when($filters['payment_method'], fn ($q, $v) => $q->where('payment_method', $v))
            ->when($filters['source'], fn ($q, $v) => $q->where('source', $v));

        // Summary of POSTED invoices in the filtered range: the owner's numbers.
        $summaryRows = (clone $base)
            ->where('status', Invoice::STATUS_POSTED)
            ->select('payment_method', DB::raw('COALESCE(SUM(total), 0) as sum'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $invoices = (clone $base)
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->with(['customer:id,name,code', 'driver:id,name'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->displayNumber(),
                'date' => $inv->invoice_date->toDateString(),
                'customer' => $inv->customer?->only(['id', 'name', 'code']),
                'source' => $inv->source,
                'payment_method' => $inv->payment_method,
                'status' => $inv->status,
                'total' => (string) $inv->total,
                'driver' => $inv->driver?->name,
            ]);

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'summary' => [
                'total' => (string) collect(['cash', 'mada', 'credit'])->reduce(
                    fn ($carry, $m) => bcadd($carry, (string) ($summaryRows[$m]->sum ?? '0'), 2),
                    '0.00',
                ),
                'cash' => (string) ($summaryRows['cash']->sum ?? '0'),
                'credit' => (string) ($summaryRows['credit']->sum ?? '0'),
                'mada' => (string) ($summaryRows['mada']->sum ?? '0'),
                'count' => (int) $summaryRows->sum('cnt'),
            ],
            'canVoid' => $request->user()->can('invoices.void'),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $invoice->load(['customer', 'driver:id,name', 'lines.product:id,name_ar', 'creditNotes.lines']);

        return Inertia::render('invoices/show', [
            'invoice' => $this->serialize($invoice),
            'canVoid' => $request->user()->can('invoices.void'),
            'canReclassify' => $request->user()->can('invoices.reclassify'),
        ]);
    }

    public function confirmDay(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $validated = $request->validate(['date' => ['required', 'date']]);

        $count = $this->service->confirmDay(Carbon::parse($validated['date']), $request->user());

        return back()->with('success', __('invoices.day_confirmed', ['count' => $count]));
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.void'), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->service->void($invoice, $request->user(), $validated['reason']);

        return back()->with('success', __('invoices.voided'));
    }

    public function reclassify(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.reclassify'), 403);

        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,mada,credit'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->reclassify($invoice, $validated['payment_method'], $request->user(), $validated['reason']);

        return back()->with('success', __('invoices.reclassified'));
    }

    public function creditNote(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoices.manage'), 403);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'items.*.condition' => ['required', 'in:good,damaged'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->createCreditNote(
            $invoice,
            $invoice->customer,
            $validated['items'],
            $validated['reason'],
            $request->user(),
        );

        return back()->with('success', __('invoices.credit_note_created'));
    }

    /** 80mm thermal receipt page (print CSS). Drivers can open their own. */
    public function thermal(Request $request, Invoice $invoice): Response
    {
        $this->authorizeView($request, $invoice);

        return Inertia::render('invoices/thermal', [
            'invoice' => $this->serialize($invoice->load(['customer', 'lines.product:id,name_ar'])),
            'bakery' => $this->bakery(),
            'backTo' => $request->user()->can('invoices.manage') ? route('invoices.index') : route('delivery.index'),
        ]);
    }

    /** A4 (tax) invoice page with the ZATCA QR. */
    public function print(Request $request, Invoice $invoice): Response
    {
        $this->authorizeView($request, $invoice);

        return Inertia::render('invoices/print', [
            'invoice' => $this->serialize($invoice->load(['customer', 'lines.product:id,name_ar,name_en'])),
            'bakery' => $this->bakery(),
        ]);
    }

    private function authorizeView(Request $request, Invoice $invoice): void
    {
        abort_unless(
            $request->user()->can('invoices.manage') || $invoice->driver_id === $request->user()->id,
            403,
        );
    }

    private function bakery(): array
    {
        $s = AppSettings::all();

        return [
            'name_ar' => $s['bakery_name_ar'],
            'name_en' => $s['bakery_name_en'],
            'vat_number' => $s['vat_number'],
            'cr_number' => $s['cr_number'],
            'national_address' => $s['national_address'],
            'phone' => $s['phone'],
            'vat_enabled' => (bool) $s['vat_enabled'],
        ];
    }

    private function serialize(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->displayNumber(),
            'uuid' => $invoice->uuid,
            'date' => $invoice->invoice_date->toDateString(),
            'posted_at' => $invoice->posted_at?->format('Y-m-d H:i'),
            'type' => $invoice->type,
            'source' => $invoice->source,
            'payment_method' => $invoice->payment_method,
            'status' => $invoice->status,
            'subtotal' => (string) $invoice->subtotal,
            'vat_amount' => (string) $invoice->vat_amount,
            'total' => (string) $invoice->total,
            'prices_include_vat' => $invoice->prices_include_vat,
            'qr_payload' => $invoice->qr_payload,
            'void_reason' => $invoice->void_reason,
            'reclass_reason' => $invoice->reclass_reason,
            'notes' => $invoice->notes,
            'driver' => $invoice->driver?->name,
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'code' => $invoice->customer->code,
                'name' => $invoice->customer->name,
                'vat_number' => $invoice->customer->vat_number,
                'national_address' => $invoice->customer->national_address,
            ] : null,
            'lines' => $invoice->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'name' => $line->product?->name_ar ?? $line->description,
                'qty' => $line->qty,
                'unit_price' => (string) $line->unit_price,
                'vat_rate' => (string) $line->vat_rate,
                'line_subtotal' => (string) $line->line_subtotal,
                'line_vat' => (string) $line->line_vat,
                'line_total' => (string) $line->line_total,
            ]),
            'credit_notes' => $invoice->relationLoaded('creditNotes')
                ? $invoice->creditNotes->map(fn ($note) => [
                    'id' => $note->id,
                    'number' => $note->displayNumber(),
                    'date' => $note->note_date->toDateString(),
                    'reason' => $note->reason,
                    'total' => (string) $note->total,
                ])
                : [],
        ];
    }
}
