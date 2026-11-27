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
    private const EMAIL_TENANT = 'tenant@example.com';
    private const EMAIL_BUDI = 'budi.penyewa@example.com';
    private const NAME_TERKINI = 'Nama Terkini';
    private const PHONE_TERKINI = '0822222222';
    private const EMAIL_TERKINI = 'terkini@example.com';

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
            'email' => self::EMAIL_TENANT,
            'password' => Hash::make($password),
            'role' => UserRole::TENANT->value,
        ]);

        // 1. Success authentication
        $authenticatedUser = $this->service->authenticate([
            'email' => self::EMAIL_TENANT,
            'password' => $password,
        ]);

        $this->assertInstanceOf(User::class, $authenticatedUser);
        $this->assertSame($user->id, $authenticatedUser->id);

        // 2. Failed authentication: Wrong password
        try {
            $this->service->authenticate([
                'email' => self::EMAIL_TENANT,
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
            'email' => self::EMAIL_BUDI,
            'phone' => '081234567890',
            'password' => 'mypassword',
        ];

        $tenant = $this->service->registerTenant($registerData);

        $this->assertInstanceOf(User::class, $tenant);
        $this->assertSame('Budi Penyewa', $tenant->name);
        $this->assertSame(self::EMAIL_BUDI, $tenant->email);
        $this->assertSame('081234567890', $tenant->phone);
        $this->assertSame(UserRole::TENANT->value, $tenant->role);
        $this->assertTrue(Hash::check('mypassword', $tenant->password));

        $this->assertDatabaseHas('users', [
            'id' => $tenant->id,
            'email' => self::EMAIL_BUDI,
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
            'name' => self::NAME_TERKINI,
            'phone' => self::PHONE_TERKINI,
            'email' => self::EMAIL_TERKINI,
        ];

        $this->service->updateProfile($user, $updateData);

        $user->refresh();
        $this->assertSame(self::NAME_TERKINI, $user->name);
        $this->assertSame(self::PHONE_TERKINI, $user->phone);
        $this->assertSame(self::EMAIL_TERKINI, $user->email);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => self::NAME_TERKINI,
            'email' => self::EMAIL_TERKINI,
            'phone' => self::PHONE_TERKINI,
        ]);
    }
}
