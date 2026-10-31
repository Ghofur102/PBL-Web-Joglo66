<?php

namespace Tests\Unit\Services\Tenant\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantAuthServiceTest extends TestCase
{
    protected TenantAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantAuthService();
    }

    public function test_authenticate(): void
    {
        $password = 'rahasia123';
        $user = User::factory()->create([
            'email' => 'tenant@example.com',
            'password' => Hash::make($password),
            'role' => UserRole::TENANT->value,
        ]);

        // 1. Success authentication
        $authenticatedUser = $this->service->authenticate([
            'email' => 'tenant@example.com',
            'password' => $password,
        ]);

        $this->assertInstanceOf(User::class, $authenticatedUser);
        $this->assertSame($user->id, $authenticatedUser->id);

        // 2. Failed authentication: Wrong password
        try {
            $this->service->authenticate([
                'email' => 'tenant@example.com',
                'password' => 'passwordsalah',
            ]);
            $this->fail('Expected ValidationException was not thrown for wrong password');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertSame('Email atau password tidak sesuai', $e->errors()['email'][0]);
        }

        // 3. Failed authentication: Non-existent email
        try {
            $this->service->authenticate([
                'email' => 'notfound@example.com',
                'password' => $password,
            ]);
            $this->fail('Expected ValidationException was not thrown for missing email');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    public function test_register_tenant(): void
    {
        $registerData = [
            'name' => 'Budi Penyewa',
            'email' => 'budi.penyewa@example.com',
            'phone' => '081234567890',
            'password' => 'mypassword',
        ];

        $tenant = $this->service->registerTenant($registerData);

        $this->assertInstanceOf(User::class, $tenant);
        $this->assertSame('Budi Penyewa', $tenant->name);
        $this->assertSame('budi.penyewa@example.com', $tenant->email);
        $this->assertSame('081234567890', $tenant->phone);
        $this->assertSame(UserRole::TENANT->value, $tenant->role);
        $this->assertTrue(Hash::check('mypassword', $tenant->password));

        $this->assertDatabaseHas('users', [
            'id' => $tenant->id,
            'email' => 'budi.penyewa@example.com',
            'role' => UserRole::TENANT->value,
        ]);
    }

    public function test_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Awal',
            'phone' => '0811111111',
            'email' => 'awal@example.com',
            'role' => UserRole::TENANT->value,
        ]);

        $updateData = [
            'name' => 'Nama Terkini',
            'phone' => '0822222222',
            'email' => 'terkini@example.com',
        ];

        $this->service->updateProfile($user, $updateData);

        $user->refresh();
        $this->assertSame('Nama Terkini', $user->name);
        $this->assertSame('0822222222', $user->phone);
        $this->assertSame('terkini@example.com', $user->email);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Terkini',
            'email' => 'terkini@example.com',
            'phone' => '0822222222',
        ]);
    }
}
