<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Employee;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSystem = filter_var($this->input('is_system'), FILTER_VALIDATE_BOOLEAN);
        $employeeId = $this->route('id');
        $employee = Employee::find($employeeId);
        $userId = $employee?->fk_user_id;

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
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ];
            $rules['password'] = 'nullable|string|min:6';
        }

        return $rules;
    }
}
