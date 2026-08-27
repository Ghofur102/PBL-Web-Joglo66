<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSystem = filter_var($this->input('is_system'), FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'name'         => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'address'      => 'nullable|string',
            'position'     => 'required|string|max:50',
            'base_salary'  => 'required|numeric|min:0',
            'status'       => 'nullable|string',
            'is_system'    => 'required|boolean',
            'role'         => 'required_if:is_system,true|string|in:worker,treasurer,owner',
            'field_ids'    => 'nullable|array',
            'field_ids.*'  => 'integer|exists:fields,id',
        ];

        if ($isSystem) {
            $rules['email']    = 'required|email|unique:users,email';
            $rules['password'] = 'required|string|min:6';
        }

        return $rules;
    }
}
