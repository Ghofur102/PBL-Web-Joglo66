<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthService
{
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw new HttpException(401, 'Email atau password salah.');
        }

        if ($user->role === UserRole::TENANT->value) {
            throw new AccessDeniedHttpException('Akses ditolak. Aplikasi ini khusus untuk Manajemen.');
        }

        if ($user->role === UserRole::WORKER->value) {
            $tableName = Schema::hasTable('field_workers') ? 'field_workers' : 'field_admins';
            $hasField = DB::table($tableName)->where('fk_user_id', $user->id)->exists();

            if (!$hasField) {
                throw new AccessDeniedHttpException('Akses ditolak. Anda belum ditugaskan untuk menjaga lapangan manapun. Silakan hubungi Owner.');
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user
        ];
    }

    public function getProfileData(User $user): User
    {
        if ($user->role === UserRole::WORKER->value || $user->role === UserRole::TREASURER->value) {
            $tableName = Schema::hasTable('field_workers') ? 'field_workers' : 'field_admins';
            $fields = DB::table($tableName)
                ->join('fields', "$tableName.fk_field_id", '=', 'fields.id')
                ->where("$tableName.fk_user_id", $user->id)
                ->pluck('fields.name')
                ->toArray();

            $user->managed_fields = empty($fields)
                ? 'Belum ditugaskan ke lapangan'
                : implode(', ', $fields);
        }

        return $user;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
