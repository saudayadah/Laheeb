<?php

namespace App\Http\Controllers;

use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $runs = PayrollRun::query()
            ->withSum('lines as total_net', 'net')
            ->withCount('lines')
            ->orderByDesc('period')
            ->paginate(24)
            ->through(fn (PayrollRun $run) => [
                'id' => $run->id,
                'period' => $run->period,
                'status' => $run->status,
                'total_net' => (string) ($run->total_net ?? '0'),
                'lines_count' => $run->lines_count,
                'paid_at' => $run->paid_at?->toDateString(),
            ]);

        return Inertia::render('payroll/index', [
            'runs' => $runs,
            'suggestedPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $run = $this->service->createRun($validated['period'], $request->user());

        return redirect()->route('payroll.show', $run)->with('success', __('common.saved'));
    }

    public function show(Request $request, PayrollRun $run): Response
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        return Inertia::render('payroll/show', [
            'run' => $this->serialize($run),
            'canApprove' => $request->user()->can('payroll.approve'),
        ]);
    }

    public function updateLine(Request $request, PayrollLine $line): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $validated = $request->validate([
            'overtime' => ['nullable', 'numeric', 'min:0'],
            'leave_allowance' => ['nullable', 'numeric', 'min:0'],
            'additions' => ['nullable', 'numeric', 'min:0'],
            'absence_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'absence_amount' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'advance_recovery' => ['nullable', 'numeric', 'min:0'],
            'charges_recovery' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,bank'],
        ]);

        $this->service->updateLine($line, $validated, $request->user());

        return back();
    }

    public function review(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $this->service->review($run, $request->user());

        return back()->with('success', __('payroll.reviewed'));
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.approve'), 403);

        $this->service->approve($run, $request->user());

        return back()->with('success', __('payroll.approved'));
    }

    public function reopen(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $this->service->reopen($run, $request->user());

        return back()->with('success', __('payroll.reopened'));
    }

    public function destroy(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $this->service->deleteRun($run, $request->user());

        return redirect()->route('payroll.index')->with('success', __('common.deleted'));
    }

    public function pay(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.approve'), 403);

        $validated = $request->validate([
            'methods' => ['nullable', 'array'],
            'methods.*' => ['in:cash,bank'],
        ]);

        $this->service->pay($run, $validated['methods'] ?? [], $request->user());

        return back()->with('success', __('payroll.paid_done'));
    }

    /** Printable payroll sheet with the signature blocks. */
    public function sheet(Request $request, PayrollRun $run): Response
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        return Inertia::render('payroll/sheet', [
            'run' => $this->serialize($run),
            'bakeryName' => (string) AppSettings::get('bakery_name_ar'),
        ]);
    }

    /** Printable payslip for one line. */
    public function payslip(Request $request, PayrollLine $line): Response
    {
        abort_unless($request->user()->can('payroll.manage'), 403);

        $line->load('employee', 'run');

        return Inertia::render('payroll/payslip', [
            'period' => $line->run->period,
            'line' => $this->serializeLine($line),
            'bakeryName' => (string) AppSettings::get('bakery_name_ar'),
        ]);
    }

    private function serialize(PayrollRun $run): array
    {
        $run->load(['lines.employee:id,name_ar,job']);

        return [
            'id' => $run->id,
            'period' => $run->period,
            'status' => $run->status,
            'paid_at' => $run->paid_at?->toDateString(),
            'lines' => $run->lines
                ->sortBy(fn (PayrollLine $line) => $line->employee?->name_ar)
                ->values()
                ->map(fn (PayrollLine $line) => $this->serializeLine($line)),
        ];
    }

    private function serializeLine(PayrollLine $line): array
    {
        return [
            'id' => $line->id,
            'employee' => [
                'id' => $line->employee?->id,
                'name_ar' => $line->employee?->name_ar,
                'job' => $line->employee?->job,
            ],
            'basic' => (string) $line->basic,
            'overtime' => (string) $line->overtime,
            'leave_allowance' => (string) $line->leave_allowance,
            'additions' => (string) $line->additions,
            'absence_days' => (string) $line->absence_days,
            'absence_amount' => (string) $line->absence_amount,
            'deductions' => (string) $line->deductions,
            'advance_recovery' => (string) $line->advance_recovery,
            'charges_recovery' => (string) $line->charges_recovery,
            'net' => (string) $line->net,
            'payment_method' => $line->payment_method,
            'paid_at' => $line->paid_at?->toDateString(),
        ];
    }
}
