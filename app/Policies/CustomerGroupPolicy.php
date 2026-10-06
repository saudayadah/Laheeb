<?php

namespace App\Policies;

use App\Models\CustomerGroup;
use App\Models\User;

class CustomerGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, CustomerGroup $customerGroup): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, CustomerGroup $customerGroup): bool
    {
        return $user->can('customers.manage');
    }

    public function delete(User $user, CustomerGroup $customerGroup): bool
    {
        return $user->can('customers.manage');
    }
}
