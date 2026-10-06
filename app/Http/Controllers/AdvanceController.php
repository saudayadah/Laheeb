<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\Employee;
use App\Models\EmployeeCharge;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvanceController extends Controller
{
    public function __construct(private PayrollService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('advances.manage'), 403);

        $advances = Advance::query()
            ->with('employee:id,name_ar')
            ->orderByDesc('advance_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Advance $a) => [
                'id' => $a->id,
                'employee' => $a->employee?->only(['id', 'name_ar']),
                'date' => $a->advance_date->toDateString(),
                'amount' => (string) $a->amount,
                'recovered' => (string) $a->recovered_total,
                'remaining' => $a->remaining(),
                'plan' => $a->plan,
                'installment' => $a->installment_amount !== null ? (string) $a->installment_amount : null,
                'paid_from' => $a->paid_from,
                'status' => $a->status,
                'notes' => $a->notes,
            ]);

        $charges = EmployeeCharge::query()
            ->with('employee:id,name_ar')
            ->orderByDesc('charge_date')
            ->limit(50)
            ->get()
            ->map(fn (EmployeeCharge $c) => [
                'id' => $c->id,
                'employee' => $c->employee?->only(['id', 'name_ar']),
                'date' => $c->charge_date->toDateString(),
                'type' => $c->type,
                'amount' => (string) $c->amount,
                'remaining' => $c->remaining(),
                'status' => $c->status,
                'notes' => $c->notes,
            ]);

        $outstanding = Advance::whereIn('status', ['active'])
            ->get()
            ->reduce(fn ($carry, $a) => $carry + (float) $a->remaining(), 0.0);

        return Inertia::render('advances/index', [
            'advances' => $advances,
            'charges' => $charges,
            'employees' => Employee::where('active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'basic_salary']),
            'outstandingTotal' => (string) $outstanding,
            'canApprove' => $request->user()->can('advances.approve'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('advances.manage'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'paid_from' => ['required', 'in:counter_cash,bank'],
            'plan' => ['required', 'in:full,installment'],
            'installment_amount' => ['nullable', 'required_if:plan,installment', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $advance = $this->service->createAdvance($validated, $request->user());

        return back()->with(
            'success',
            $advance->status === 'pending' ? __('advances.pending_approval') : __('common.saved'),
        );
    }

    public function approve(Request $request, Advance $advance): RedirectResponse
    {
        abort_unless($request->user()->can('advances.approve'), 403);

        $this->service->approveAdvance($advance, $request->user());

        return back()->with('success', __('advances.approved'));
    }

    public function storeCharge(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('advances.manage'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'charge_date' => ['required', 'date'],
            'type' => ['required', 'in:fine,damage,shortage'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->createCharge($validated, $request->user());

        return back()->with('success', __('advances.charge_pending'));
    }

    public function approveCharge(Request $request, EmployeeCharge $charge): RedirectResponse
    {
        // Cash shortages reach payroll only with the Owner's approval (Q12).
        $permission = $charge->type === 'shortage' ? 'advances.approve' : 'advances.manage';
        abort_unless($request->user()->can($permission), 403);

        $this->service->approveCharge($charge, $request->user());

        return back()->with('success', __('advances.charge_approved'));
    }
}
