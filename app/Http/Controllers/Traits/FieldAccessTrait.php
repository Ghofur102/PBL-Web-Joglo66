<?php

namespace App\Http\Controllers\Traits;

use App\Models\Field;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait FieldAccessTrait
{
    protected function getAccessibleFieldIds(User $user): array
    {
        if ($user->role === 'owner') {
            return Field::query()->where('fk_user_id', $user->id)->pluck('id')->toArray();
        }

        if (in_array($user->role, ['worker', 'treasurer'], true)) {
            return DB::table('field_workers')
                ->where('fk_user_id', $user->id)
                ->pluck('fk_field_id')
                ->toArray();
        }

        return [];
    }

    protected function checkFieldAccess(?User $user, int $fieldId): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'owner') {
            return Field::query()->where('id', $fieldId)->where('fk_user_id', $user->id)->exists();
        }

        if (in_array($user->role, ['worker', 'treasurer'], true)) {
            return DB::table('field_workers')
                ->where('fk_user_id', $user->id)
                ->where('fk_field_id', $fieldId)
                ->exists();
        }

        return false;
    }
}
