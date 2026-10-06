<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('employees.manage'), 403);

        $employees = Employee::query()
            ->with('user:id,name')
            ->orderBy('name_ar')
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name_ar' => $e->name_ar,
                'name_en' => $e->name_en,
                'nationality' => $e->nationality,
                'iqama_number' => $e->iqama_number,
                'iqama_expiry' => $e->iqama_expiry?->toDateString(),
                'iqama_expiring' => $e->iqama_expiry !== null && $e->iqama_expiry->lte(today()->addDays(60)),
                'job' => $e->job,
                'basic_salary' => (string) $e->basic_salary,
                'join_date' => $e->join_date?->toDateString(),
                'active' => $e->active,
                'user' => $e->user?->only(['id', 'name']),
                'outstanding' => $e->outstanding(),
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'users' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
            'totalSalaries' => (string) $employees->where('active', true)->sum(fn ($e) => (float) $e['basic_salary']),
            'totalOutstanding' => (string) $employees->sum(fn ($e) => (float) $e['outstanding']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('employees.manage'), 403);

        Employee::create($this->validated($request));

        return back()->with('success', __('common.saved'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->can('employees.manage'), 403);

        $data = $this->validated($request);

        // An empty iqama field keeps the stored (encrypted) value.
        if (($data['iqama_number'] ?? '') === '') {
            unset($data['iqama_number']);
        }

        $employee->update($data);

        return back()->with('success', __('common.saved'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'iqama_number' => ['nullable', 'string', 'max:20'],
            'iqama_expiry' => ['nullable', 'date'],
            'job' => ['required', 'in:baker,driver,worker,other'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'join_date' => ['nullable', 'date'],
            'active' => ['boolean'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }
}
