<?php

namespace App\Policies;

use App\Models\DeliveryRoute;
use App\Models\User;

class DeliveryRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('routes.manage');
    }

    public function update(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $user->can('routes.manage');
    }

    public function delete(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $user->can('routes.manage');
    }
}
