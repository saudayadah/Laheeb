<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Receipt;
use App\Services\ReceiptService;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('receipts.manage'), 403);

        $filters = [
            'from' => $request->input('from', today()->startOfMonth()->toDateString()),
            'to' => $request->input('to', today()->toDateString()),
            'search' => $request->string('search')->toString(),
            'method' => $request->input('method'),
        ];

        $base = Receipt::query()
            ->whereDate('receipt_date', '>=', $filters['from'])
            ->whereDate('receipt_date', '<=', $filters['to'])
            ->when($filters['search'], fn ($q, $s) => $q->whereHas(
                'customer',
                fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"),
            ))
            ->when($filters['method'], fn ($q, $v) => $q->where('method', $v));

        $summaryRows = (clone $base)
            ->where('status', 'posted')
            ->select('method', DB::raw('COALESCE(SUM(amount), 0) as sum'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        $receipts = (clone $base)
            ->with(['customer:id,name,code', 'group:id,name', 'receiver:id,name'])
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Receipt $r) => [
                'id' => $r->id,
                'number' => $r->displayNumber(),
                'date' => $r->receipt_date->toDateString(),
                'customer' => $r->customer?->only(['id', 'name', 'code']),
                'group' => $r->group?->only(['id', 'name']),
                'amount' => (string) $r->amount,
                'method' => $r->method,
                'status' => $r->status,
                'receiver' => $r->receiver?->name,
                'reference' => $r->reference,
            ]);

        return Inertia::render('receipts/index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'summary' => [
                'total' => (string) $summaryRows->sum(fn ($r) => (float) $r->sum),
                'cash' => (string) ($summaryRows['cash']->sum ?? '0'),
                'mada' => (string) ($summaryRows['mada']->sum ?? '0'),
                'transfer' => (string) ($summaryRows['transfer']->sum ?? '0'),
                'count' => (int) $summaryRows->sum('cnt'),
            ],
            'customers' => Customer::where('active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'groups' => CustomerGroup::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Open invoices for the allocation preview (JSON). */
    public function openInvoices(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('receipts.manage'), 403);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
        ]);

        $customer = ! empty($validated['customer_id']) ? Customer::find($validated['customer_id']) : null;
        $group = ! empty($validated['customer_group_id']) ? CustomerGroup::find($validated['customer_group_id']) : null;

        $rows = $this->service->openInvoices($customer, $group)->map(fn ($row) => [
            'invoice_id' => $row['invoice']->id,
            'number' => $row['invoice']->displayNumber(),
            'date' => $row['invoice']->invoice_date->toDateString(),
            'customer' => $row['invoice']->customer?->name,
            'total' => (string) $row['invoice']->total,
            'open' => $row['open'],
        ]);

        return response()->json(['invoices' => $rows]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('receipts.manage'), 403);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id', 'required_without:customer_id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:cash,mada,transfer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['numeric', 'min:0.01', 'max:10000000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $receipt = $this->service->create(
            $validated,
            $validated['allocations'] ?? null,
            $request->user(),
            $validated['idempotency_key'],
        );

        return redirect()
            ->route('receipts.voucher', $receipt)
            ->with('success', __('receipts.created', ['number' => $receipt->displayNumber()]));
    }

    public function voucher(Request $request, Receipt $receipt): Response
    {
        abort_unless(
            $request->user()->can('receipts.manage') || $receipt->received_by === $request->user()->id,
            403,
        );

        $receipt->load(['customer:id,name,code', 'group:id,name', 'receiver:id,name', 'allocations.invoice']);

        return Inertia::render('receipts/voucher', [
            'receipt' => [
                'id' => $receipt->id,
                'number' => $receipt->displayNumber(),
                'date' => $receipt->receipt_date->toDateString(),
                'amount' => (string) $receipt->amount,
                'method' => $receipt->method,
                'reference' => $receipt->reference,
                'status' => $receipt->status,
                'customer' => $receipt->customer?->only(['id', 'name', 'code']),
                'group' => $receipt->group?->only(['id', 'name']),
                'receiver' => $receipt->receiver?->name,
                'notes' => $receipt->notes,
                'allocations' => $receipt->allocations->map(fn ($a) => [
                    'invoice' => $a->invoice->displayNumber(),
                    'amount' => (string) $a->amount,
                ]),
            ],
            'bakeryName' => (string) AppSettings::get('bakery_name_ar'),
            'backTo' => $request->user()->can('receipts.manage') ? route('receipts.index') : route('delivery.index'),
        ]);
    }

    public function void(Request $request, Receipt $receipt): RedirectResponse
    {
        abort_unless($request->user()->can('receipts.manage'), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->service->void($receipt, $request->user(), $validated['reason']);

        return back()->with('success', __('receipts.voided'));
    }
}
