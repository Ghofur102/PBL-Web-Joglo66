<?php

namespace App\Http\Requests\Owner;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OwnerFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::OWNER->value;
    }

    protected function prepareForValidation(): void
    {
        $rules = $this->input('refund_rules');
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['refund_rules' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'category' => 'required|in:futsal,mini soccer',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:255',
            'min_cancel_days' => 'nullable|integer|min:0|max:30',
            'min_reschedule_days' => 'nullable|integer|min:0|max:30',
            'max_reschedule_times' => 'nullable|integer|min:1|max:10',
            'refund_rules' => 'sometimes|array|min:2',
            'refund_rules.*' => 'required|array:rule_type,min_hours_before,refund_percent,is_active',
            'refund_rules.*.rule_type' => 'required|in:cancel,reschedule',
            'refund_rules.*.min_hours_before' => 'required|integer|min:0|max:4294967295',
            'refund_rules.*.refund_percent' => 'required|integer|min:0|max:100',
            'refund_rules.*.is_active' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rules = $this->input('refund_rules');
            if (! is_array($rules)) {
                return;
            }

            $types = [];
            $thresholds = [];
            foreach ($rules as $rule) {
                if (! is_array($rule) || ! isset($rule['rule_type'], $rule['min_hours_before'])) {
                    continue;
                }

                $type = $rule['rule_type'];
                $types[$type] = true;
                $key = $type.':'.$rule['min_hours_before'];
                if (isset($thresholds[$key])) {
                    $validator->errors()->add('refund_rules', 'Batas waktu aturan refund tidak boleh duplikat.');
                    break;
                }
                $thresholds[$key] = true;
            }

            if (count(array_intersect(['cancel', 'reschedule'], array_keys($types))) !== 2) {
                $validator->errors()->add('refund_rules', 'Aturan cancel dan reschedule wajib diisi.');
            }
        });
    }
}
