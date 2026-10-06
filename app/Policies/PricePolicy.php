<?php

namespace App\Policies;

use App\Models\Price;
use App\Models\User;

class PricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('prices.manage');
    }

    public function update(User $user, Price $price): bool
    {
        return $user->can('prices.manage');
    }

    public function delete(User $user, Price $price): bool
    {
        return $user->can('prices.manage');
    }
}
