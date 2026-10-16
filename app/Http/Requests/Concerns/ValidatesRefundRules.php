<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesRefundRules
{
    protected function prepareRefundRulesInput(): void
    {
        $rules = $this->input('refund_rules');
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['refund_rules' => $decoded]);
            }
        }
    }

    protected function refundRulesValidationRules(): array
    {
        return [
            'refund_rules' => 'sometimes|array|min:2',
            'refund_rules.*' => 'required|array:rule_type,min_hours_before,refund_percent,is_active',
            'refund_rules.*.rule_type' => 'required|in:cancel,reschedule',
            'refund_rules.*.min_hours_before' => 'required|integer|min:0|max:4294967295',
            'refund_rules.*.refund_percent' => 'required|integer|min:0|max:100',
            'refund_rules.*.is_active' => 'sometimes|boolean',
        ];
    }

    protected function validateRefundRuleTiers(Validator $validator): void
    {
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
    }
}
