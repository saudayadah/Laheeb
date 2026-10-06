<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized by the controller via DeliveryRoutePolicy.
    }

    public function rules(): array
    {
        $routeId = $this->route('delivery_route')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('delivery_routes', 'name')->ignore($routeId)],
            'city' => ['nullable', 'string', 'max:100'],
            'default_driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
