<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->roles->first()?->name,
                'active' => $u->active,
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => ['owner', 'accountant', 'clerk', 'driver'],
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'active' => $data['active'] ?? true,
            ]);

            $user->assignRole($data['role']);
        });

        return redirect()->route('users.index')->with('success', __('common.saved'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        DB::transaction(function () use ($data, $user, $request) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            // Nobody deactivates themselves.
            if ($user->id !== $request->user()->id) {
                $user->active = $data['active'] ?? $user->active;
            }

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            // The owner's own role cannot be changed away by themselves.
            if ($user->id !== $request->user()->id) {
                $user->syncRoles([$data['role']]);
            }
        });

        return redirect()->route('users.index')->with('success', __('common.saved'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // Users are never deleted, only deactivated, so history stays intact.
        $user->update(['active' => false]);

        return redirect()->route('users.index')->with('success', __('common.saved'));
    }
}
