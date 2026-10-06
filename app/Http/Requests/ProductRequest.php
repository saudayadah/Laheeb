<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized by the controller via ProductPolicy.
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'category' => ['required', Rule::in(ProductCategory::values())],
            'size_cm' => ['nullable', 'integer', 'min:1', 'max:200'],
            'unit' => ['required', 'string', 'max:20'],
            'default_price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
