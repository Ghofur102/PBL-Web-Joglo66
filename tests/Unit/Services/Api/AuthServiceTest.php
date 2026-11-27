<?php

namespace Tests\Unit\Services\Api;

use App\Enums\UserRole;
use App\Models\Field;
use App\Models\FieldWorker;
use App\Models\User;
use App\Services\Api\AuthService;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private const EMAIL_TENANT = 'tenant@example.com';
    private const EMAIL_WORKER = 'worker@example.com';

    protected AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    public function test_login(): void
    {
        $password = 'password123';

        // 1. Invalid credentials test (wrong email & wrong password)
        try {
            $this->service->login(['email' => 'nonexistent@example.com', 'password' => 'wrongpass']);
            $this->fail('Expected HttpException 401 for invalid email was not thrown');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('Email atau password salah.', $e->getMessage());
        }

        User::factory()->create([
            'email' => self::EMAIL_TENANT,
            'password' => Hash::make($password),
            'role' => UserRole::TENANT->value,
        ]);

        try {
            $this->service->login(['email' => self::EMAIL_TENANT, 'password' => 'wrongpassword']);
            $this->fail('Expected HttpException 401 for wrong password was not thrown');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }

        // 2. Denied role test (tenant cannot access management API)
        try {
            $this->service->login(['email' => self::EMAIL_TENANT, 'password' => $password]);
            $this->fail('Expected AccessDeniedHttpException for tenant role was not thrown');
        } catch (AccessDeniedHttpException $e) {
            $this->assertSame('Akses ditolak. Aplikasi ini khusus untuk Manajemen.', $e->getMessage());
        }

        // 3. Worker without assigned field test
        $worker = User::factory()->create([
            'email' => self::EMAIL_WORKER,
            'password' => Hash::make($password),
            'role' => UserRole::WORKER->value,
        ]);

        try {
            $this->service->login(['email' => self::EMAIL_WORKER, 'password' => $password]);
            $this->fail('Expected AccessDeniedHttpException for unassigned worker was not thrown');
        } catch (AccessDeniedHttpException $e) {
            $this->assertStringContainsString('Anda belum ditugaskan untuk menjaga lapangan', $e->getMessage());
        }

        // 4. Successful login test for assigned worker
        $field = Field::factory()->create(['name' => 'Lapangan A']);
        FieldWorker::create([
            'fk_field_id' => $field->id,
            'fk_user_id' => $worker->id,
        ]);

        $result = $this->service->login(['email' => self::EMAIL_WORKER, 'password' => $password]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertSame($worker->id, $result['user']->id);

        // 5. Successful login test for owner (does not require field assignment)
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make($password),
            'role' => UserRole::OWNER->value,
        ]);

        $ownerResult = $this->service->login(['email' => 'owner@example.com', 'password' => $password]);
        $this->assertNotEmpty($ownerResult['token']);
        $this->assertSame($owner->id, $ownerResult['user']->id);
    }

    public function test_get_profile_data(): void
    {
        $field1 = Field::factory()->create(['name' => 'Lapangan Futsal']);
        $field2 = Field::factory()->create(['name' => 'Lapangan Badminton']);

        // 1. Worker with assigned fields
        $worker = User::factory()->create(['role' => UserRole::WORKER->value]);
        FieldWorker::create(['fk_field_id' => $field1->id, 'fk_user_id' => $worker->id]);
        FieldWorker::create(['fk_field_id' => $field2->id, 'fk_user_id' => $worker->id]);

        $profileWorker = $this->service->getProfileData($worker);
        $this->assertStringContainsString('Lapangan Futsal', $profileWorker->managed_fields);
        $this->assertStringContainsString('Lapangan Badminton', $profileWorker->managed_fields);

        // 2. Treasurer without assigned fields
        $treasurer = User::factory()->create(['role' => UserRole::TREASURER->value]);
        $profileTreasurer = $this->service->getProfileData($treasurer);
        $this->assertSame('Belum ditugaskan ke lapangan', $profileTreasurer->managed_fields);

        // 3. Owner role (profile returned without managed_fields altered)
        $owner = User::factory()->create(['role' => UserRole::OWNER->value]);
        $profileOwner = $this->service->getProfileData($owner);
        $this->assertNull($profileOwner->managed_fields ?? null);
    }

    public function test_logout(): void
    {
        $user = User::factory()->create(['role' => UserRole::OWNER->value]);
        $newAccessToken = $user->createToken('test_token');

        $user->withAccessToken($newAccessToken->accessToken);

        $this->assertSame(1, $user->tokens()->count());

        $this->service->logout($user);

        $this->assertSame(0, $user->tokens()->count());
    }
}
