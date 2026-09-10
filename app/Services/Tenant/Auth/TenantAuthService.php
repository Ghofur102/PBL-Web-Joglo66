<?php

namespace App\Services\Tenant\Auth;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class TenantAuthService
{
    public function authenticate(array $credentials): User
    {
        if (!Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai',
            ]);
        }

        return User::where('email', $credentials['email'])->first();
    }

    public function registerTenant(array $data): User
    {
        return User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'],
            'password' => Hash::make($data['password']),
            'role'     => UserRole::TENANT->value,
        ]);
    }

    public function updateProfile(User $user, array $data): void
    {
        $user->update([
            'name'  => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
        ]);
    }
}
