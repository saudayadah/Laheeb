<?php

namespace App\Http\Controllers;

use App\Models\DailyClose;
use App\Models\User;
use App\Services\DailyCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DailyCloseController extends Controller
{
    public function __construct(private DailyCloseService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isApprover = $user->can('closes.approve');

        abort_unless($isApprover || $user->can('deliveries.own'), 403);

        $closes = DailyClose::query()
            ->when(! $isApprover, fn ($q) => $q->where('closeable_type', DailyClose::TYPE_DRIVER)->where('closeable_id', $user->id))
            ->with('driver:id,name')
            ->orderByDesc('close_date')
            ->orderBy('closeable_type')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DailyClose $close) => [
                'id' => $close->id,
                'date' => $close->close_date->toDateString(),
                'type' => $close->closeable_type,
                'closeable_id' => (int) $close->closeable_id,
                'driver' => $close->closeable_type === DailyClose::TYPE_DRIVER ? $close->driver?->name : null,
                'expected' => (string) $close->expected,
                'counted' => (string) $close->counted,
                'variance' => (string) $close->variance,
                'status' => $close->status,
            ]);

        return Inertia::render('closes/index', [
            'closes' => $closes,
            'isApprover' => $isApprover,
            'drivers' => $isApprover
                ? User::role('driver')->where('active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'selfId' => $user->id,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'in:driver,counter'],
            'id' => ['required_if:type,driver', 'nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $type = $validated['type'];
        $id = $type === DailyClose::TYPE_DRIVER ? (int) $validated['id'] : 0;
        $date = Carbon::parse($validated['date'] ?? today());

        $this->authorizeClose($request, $type, $id);

        $existing = DailyClose::whereDate('close_date', $date)
            ->where('closeable_type', $type)
            ->where('closeable_id', $id)
            ->with('denominations')
            ->first();

        $expected = $type === DailyClose::TYPE_DRIVER
            ? $this->service->expectedForDriver($id, $date)
            : $this->service->expectedForCounter($date);

        return Inertia::render('closes/form', [
            'type' => $type,
            'closeableId' => $id,
            'driverName' => $type === DailyClose::TYPE_DRIVER ? User::find($id)?->name : null,
            'date' => $date->toDateString(),
            'expected' => $expected,
            'breakdown' => $this->service->breakdown($type, $id, $date),
            'denominations' => DailyClose::DENOMINATIONS,
            'existing' => $existing ? [
                'id' => $existing->id,
                'status' => $existing->status,
                'counted' => (string) $existing->counted,
                'variance' => (string) $existing->variance,
                'notes' => $existing->notes,
                'counts' => $existing->denominations->pluck('count', 'denomination'),
            ] : null,
            'canApprove' => $request->user()->can('closes.approve'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:driver,counter'],
            'id' => ['required_if:type,driver', 'nullable', 'integer'],
            'date' => ['required', 'date'],
            'counts' => ['required', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $type = $validated['type'];
        $id = $type === DailyClose::TYPE_DRIVER ? (int) $validated['id'] : 0;

        $this->authorizeClose($request, $type, $id);

        $close = $this->service->submit(
            $type,
            $id,
            Carbon::parse($validated['date']),
            array_map('intval', $validated['counts']),
            $validated['notes'] ?? null,
            $request->user(),
        );

        return redirect()
            ->route('closes.create', ['type' => $type, 'id' => $id ?: null, 'date' => $close->close_date->toDateString()])
            ->with('success', __('closes.submitted'));
    }

    public function approve(Request $request, DailyClose $close): RedirectResponse
    {
        abort_unless($request->user()->can('closes.approve'), 403);

        $this->service->approve($close, $request->user());

        return back()->with('success', __('closes.approved'));
    }

    private function authorizeClose(Request $request, string $type, int $id): void
    {
        $user = $request->user();

        if ($user->can('closes.approve')) {
            return;
        }

        // A driver may only count his own cash.
        abort_unless(
            $user->can('deliveries.own') && $type === DailyClose::TYPE_DRIVER && $id === $user->id,
            403,
        );
    }
}
