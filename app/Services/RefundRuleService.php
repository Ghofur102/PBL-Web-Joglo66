<?php

namespace App\Services;

use App\Models\Field;
use App\Models\FieldRefundRule;
use Illuminate\Validation\ValidationException;

class RefundRuleService
{
    public static function defaultRules(): array
    {
        return [
            [
                'rule_type' => 'cancel',
                'min_hours_before' => 72,
                'refund_percent' => 100,
                'is_active' => true,
            ],
            [
                'rule_type' => 'reschedule',
                'min_hours_before' => 72,
                'refund_percent' => 100,
                'is_active' => true,
            ],
        ];
    }

    public function syncRules(Field $field, array $rules): void
    {
        $seen = [];
        $types = [];

        foreach ($rules as $rule) {
            $type = $rule['rule_type'] ?? null;
            $hours = $rule['min_hours_before'] ?? null;

            if (! in_array($type, ['cancel', 'reschedule'], true)
                || ! is_int($hours)
                || $hours < 0
                || ! isset($rule['refund_percent'])
                || ! is_int($rule['refund_percent'])
                || $rule['refund_percent'] < 0
                || $rule['refund_percent'] > 100) {
                throw ValidationException::withMessages([
                    'refund_rules' => 'Aturan refund tidak valid.',
                ]);
            }

            $key = $type.':'.$hours;
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'refund_rules' => 'Batas waktu aturan refund tidak boleh duplikat.',
                ]);
            }

            $seen[$key] = true;
            $types[$type] = true;
        }

        if (count($types) !== 2) {
            throw ValidationException::withMessages([
                'refund_rules' => 'Aturan cancel dan reschedule wajib diisi.',
            ]);
        }

        $field->refundRules()->delete();

        foreach ($rules as $rule) {
            $field->refundRules()->create([
                'rule_type' => $rule['rule_type'],
                'min_hours_before' => $rule['min_hours_before'],
                'refund_percent' => $rule['refund_percent'],
                'is_active' => $rule['is_active'] ?? true,
            ]);
        }
    }

    public function resolvePercent(int $fieldId, string $type, int $hoursBefore): ?int
    {
        if (! in_array($type, ['cancel', 'reschedule'], true)) {
            throw new \InvalidArgumentException('Jenis aturan refund tidak valid.');
        }

        $percent = FieldRefundRule::query()
            ->where('fk_field_id', $fieldId)
            ->where('rule_type', $type)
            ->where('is_active', true)
            ->where('min_hours_before', '<=', $hoursBefore)
            ->orderByDesc('min_hours_before')
            ->value('refund_percent');

        return $percent === null ? null : (int) $percent;
    }
}
