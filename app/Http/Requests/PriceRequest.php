<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized by the controller via PricePolicy.
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'effective_from' => ['required', 'date'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasScope = $this->filled('customer_id')
                    || $this->filled('customer_group_id')
                    || $this->filled('product_id');

                if (! $hasScope) {
                    $validator->errors()->add('price', __('validation.required', ['attribute' => __('validation.attributes.customer_id')]));
                }

                if ($this->filled('customer_id') && $this->filled('customer_group_id')) {
                    $validator->errors()->add('customer_group_id', __('validation.in', ['attribute' => __('validation.attributes.customer_group_id')]));
                }
            },
        ];
    }
}
