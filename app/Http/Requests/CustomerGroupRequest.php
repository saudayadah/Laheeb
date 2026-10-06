<?php

namespace App\Http\Requests;

use App\Enums\CreditScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized by the controller via CustomerGroupPolicy.
    }

    public function rules(): array
    {
        $groupId = $this->route('group')?->id;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('customer_groups', 'name')->ignore($groupId)],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'credit_scope' => ['required', Rule::in(CreditScope::values())],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
