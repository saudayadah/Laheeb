<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('employees.manage'), 403);

        $leaves = EmployeeLeave::query()
            ->with('employee:id,name_ar,job')
            ->orderByRaw('actual_return IS NULL DESC') // open leaves first
            ->orderByDesc('start_date')
            ->limit(100)
            ->get()
            ->map(fn (EmployeeLeave $leave) => [
                'id' => $leave->id,
                'employee' => $leave->employee?->only(['id', 'name_ar', 'job']),
                'start_date' => $leave->start_date->toDateString(),
                'expected_return' => $leave->expected_return->toDateString(),
                'actual_return' => $leave->actual_return?->toDateString(),
                'overdue' => $leave->isOverdue(),
                'away' => $leave->actual_return === null,
                'notes' => $leave->notes,
            ]);

        return Inertia::render('leaves/index', [
            'leaves' => $leaves,
            'employees' => Employee::where('active', true)->orderBy('name_ar')->get(['id', 'name_ar']),
            'awayCount' => $leaves->where('away', true)->count(),
            'overdueCount' => $leaves->where('overdue', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('employees.manage'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'expected_return' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        EmployeeLeave::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('success', __('common.saved'));
    }

    /** The employee is back — record the real return date. */
    public function markReturned(Request $request, EmployeeLeave $leave): RedirectResponse
    {
        abort_unless($request->user()->can('employees.manage'), 403);

        $validated = $request->validate([
            'actual_return' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $leave->update(['actual_return' => $validated['actual_return']]);

        return back()->with('success', __('common.saved'));
    }
}
