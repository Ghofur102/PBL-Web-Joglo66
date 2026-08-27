<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait FieldAccessTrait
{
    protected function getAccessibleFieldIds(User $user): array
    {
        if ($user->hasRestrictedFieldAccess()) {
            return $user->fieldWorker()
                ->pluck('fk_field_id')
                ->toArray();
        }

        return [];
    }

    protected function checkFieldAccess(?User $user, int $fieldId): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'owner') {
            return true;
        }

        $return = false;

        if ($user->hasRestrictedFieldAccess()) {
            $return = DB::table('field_workers')
                ->where('fk_user_id', $user->id)
                ->where('fk_field_id', $fieldId)
                ->exists();
        }

        return $return;
    }
}
