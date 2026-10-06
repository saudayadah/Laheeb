<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BakerySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'bakery_name_ar' => ['required', 'string', 'max:150'],
            'bakery_name_en' => ['nullable', 'string', 'max:150'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'cr_number' => ['nullable', 'string', 'max:20'],
            'national_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'vat_enabled' => ['required', 'boolean'],
            'prices_include_vat' => ['required', 'boolean'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_credit_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }
}
