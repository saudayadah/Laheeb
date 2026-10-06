<?php

namespace App\Http\Requests;

use App\Enums\CustomerType;
use App\Enums\PaymentTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized by the controller via CustomerPolicy.
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'code')->ignore($customerId)],
            'name' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'type' => ['required', Rule::in(CustomerType::values())],
            'payment_term' => ['required', Rule::in(PaymentTerm::values())],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'delivery_route_id' => ['nullable', 'integer', 'exists:delivery_routes,id'],
            'stop_sequence' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'map_url' => ['nullable', 'string', 'max:500'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'cr_number' => ['nullable', 'string', 'max:20'],
            'national_address' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Only used on create, and only when the user may manage prices.
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }
}
