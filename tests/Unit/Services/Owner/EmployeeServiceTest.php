<?php

namespace Tests\Unit\Services\Owner;

use App\Enums\GeneralStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\Field;
use App\Models\FieldWorker;
use App\Models\User;
use App\Services\Owner\EmployeeService;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class EmployeeServiceTest extends TestCase
{
    protected EmployeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmployeeService();
    }

    public function test_get_employees_list(): void
    {
        $field = Field::factory()->create(['name' => 'Lapangan A']);
        $user = User::factory()->create([
            'role' => UserRole::WORKER->value,
            'email' => 'worker1@example.com',
        ]);
        FieldWorker::create([
            'fk_field_id' => $field->id,
            'fk_user_id' => $user->id,
        ]);

        $employeeWithUser = Employee::create([
            'fk_user_id' => $user->id,
            'name' => 'Budi Santoso',
            'phone_number' => '08123456789',
            'address' => 'Jl. Merdeka No. 1',
            'position' => 'Penjaga Lapangan',
            'base_salary' => 2500000,
            'join_date' => '2026-01-01',
            'status' => GeneralStatus::ACTIVE->value,
        ]);

        $employeeWithoutUser = Employee::create([
            'fk_user_id' => null,
            'name' => 'Siti Aminah',
            'phone_number' => '08987654321',
            'address' => 'Jl. Mawar No. 2',
            'position' => 'Cleaning Service',
            'base_salary' => 2000000,
            'join_date' => '2026-02-01',
            'status' => GeneralStatus::ACTIVE->value,
        ]);

        $list = $this->service->getEmployeesList();

        $this->assertCount(2, $list);

        $budi = collect($list)->firstWhere('id', $employeeWithUser->id);
        $this->assertNotNull($budi);
        $this->assertTrue($budi['is_system']);
        $this->assertSame('Lapangan A', $budi['field_names']);
        $this->assertContains($field->id, $budi['field_ids']);
        $this->assertSame('worker1@example.com', $budi['email']);

        $siti = collect($list)->firstWhere('id', $employeeWithoutUser->id);
        $this->assertNotNull($siti);
        $this->assertFalse($siti['is_system']);
        $this->assertNull($siti['email']);
        $this->assertSame('', $siti['field_names']);
    }

    public function test_store_employee(): void
    {
        $field = Field::factory()->create();

        // 1. Store employee with user account (is_system = true)
        $dataWithSystem = [
            'name' => 'Agus Karyawan',
            'email' => 'agus@example.com',
            'password' => 'secret123',
            'role' => UserRole::WORKER->value,
            'phone_number' => '081122334455',
            'address' => 'Jl. Pahlawan No. 5',
            'position' => 'Operator',
            'base_salary' => 3000000,
            'is_system' => true,
            'field_ids' => [$field->id],
        ];

        $employee = $this->service->storeEmployee($dataWithSystem);

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertNotNull($employee->fk_user_id);
        $this->assertSame('Agus Karyawan', $employee->name);
        $this->assertSame(GeneralStatus::ACTIVE->value, $employee->status);

        $user = User::find($employee->fk_user_id);
        $this->assertNotNull($user);
        $this->assertSame('agus@example.com', $user->email);
        $this->assertTrue(Hash::check('secret123', $user->password));

        $this->assertDatabaseHas('field_workers', [
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
        ]);

        // 2. Store employee without user account (is_system = false)
        $dataWithoutSystem = [
            'name' => 'Rini CS',
            'position' => 'Staff Kebersihan',
            'base_salary' => 1800000,
            'is_system' => false,
        ];

        $employeeNoUser = $this->service->storeEmployee($dataWithoutSystem);
        $this->assertNull($employeeNoUser->fk_user_id);
    }

    public function test_update_employee(): void
    {
        $field = Field::factory()->create();
        $employee = Employee::create([
            'fk_user_id' => null,
            'name' => 'Nama Lama',
            'position' => 'Staff',
            'base_salary' => 2000000,
            'join_date' => now()->toDateString(),
            'status' => GeneralStatus::ACTIVE->value,
        ]);

        // 1. Update from non-system to system user
        $updateData = [
            'name' => 'Nama Baru',
            'email' => 'namabaru@example.com',
            'password' => 'newpass456',
            'role' => UserRole::WORKER->value,
            'position' => 'Senior Staff',
            'base_salary' => 2500000,
            'is_system' => true,
            'field_ids' => [$field->id],
        ];

        $this->service->updateEmployee($employee, $updateData);

        $employee->refresh();
        $this->assertSame('Nama Baru', $employee->name);
        $this->assertSame(2500000, $employee->base_salary);
        $this->assertNotNull($employee->fk_user_id);

        $this->assertDatabaseHas('field_workers', [
            'fk_user_id' => $employee->fk_user_id,
            'fk_field_id' => $field->id,
        ]);

        // 2. Update from system user to non-system (removes user account)
        $userIdToRemove = $employee->fk_user_id;
        $removeSystemData = [
            'name' => 'Nama Baru Non-System',
            'position' => 'Staff Biasa',
            'base_salary' => 2200000,
            'is_system' => false,
        ];

        $this->service->updateEmployee($employee, $removeSystemData);

        $employee->refresh();
        $this->assertNull($employee->fk_user_id);
        $this->assertDatabaseMissing('users', ['id' => $userIdToRemove]);
        $this->assertDatabaseMissing('field_workers', ['fk_user_id' => $userIdToRemove]);
    }

    public function test_destroy_employee(): void
    {
        $employee = Employee::create([
            'name' => 'Karyawan Gaji',
            'position' => 'Staff',
            'base_salary' => 2500000,
            'join_date' => now()->toDateString(),
            'status' => GeneralStatus::ACTIVE->value,
        ]);

        // 1. Cannot delete when salary records exist
        $field = Field::factory()->create();
        $owner = User::factory()->create(['role' => UserRole::OWNER->value]);
        $expense = Expense::create([
            'fk_field_id' => $field->id,
            'fk_user_id' => $owner->id,
            'name' => 'Gaji Karyawan',
            'category' => 'operasional',
            'quantity' => 1,
            'unit_price' => 2500000,
            'expense_date' => now()->toDateString(),
            'generate_at' => now(),
        ]);

        EmployeeSalary::create([
            'fk_employee_id' => $employee->id,
            'fk_expense_id' => $expense->id,
            'amount_paid' => 2500000,
            'period_month' => 'october',
            'period_year' => 2026,
            'payment_date' => now()->toDateString(),
        ]);

        try {
            $this->service->destroyEmployee($employee);
            $this->fail('Expected ConflictHttpException was not thrown');
        } catch (ConflictHttpException $e) {
            $this->assertSame('Karyawan tidak dapat dihapus karena memiliki riwayat penggajian.', $e->getMessage());
        }

        // 2. Successfully delete when no salary records exist
        $user = User::factory()->create(['role' => UserRole::WORKER->value]);
        $field = Field::factory()->create();
        FieldWorker::create(['fk_field_id' => $field->id, 'fk_user_id' => $user->id]);

        $deletableEmployee = Employee::create([
            'fk_user_id' => $user->id,
            'name' => 'Karyawan Hapus',
            'position' => 'Staff',
            'base_salary' => 2000000,
            'join_date' => now()->toDateString(),
            'status' => GeneralStatus::ACTIVE->value,
        ]);

        $this->service->destroyEmployee($deletableEmployee);

        $this->assertDatabaseMissing('employees', ['id' => $deletableEmployee->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('field_workers', ['fk_user_id' => $user->id]);
    }

    public function test_sync_user_account(): void
    {
        $method = new ReflectionMethod(EmployeeService::class, 'syncUserAccount');
        $method->setAccessible(true);

        $data = [
            'name' => 'Sync Test',
            'email' => 'sync@example.com',
            'password' => 'secret',
            'role' => UserRole::WORKER->value,
        ];

        // Case 1: is_system = true, creates user
        $userId = $method->invoke($this->service, null, true, $data);
        $this->assertNotNull($userId);
        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'sync@example.com']);

        // Case 2: is_system = false, removes user
        $result = $method->invoke($this->service, $userId, false, $data);
        $this->assertNull($result);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_create_or_update_system_user(): void
    {
        $method = new ReflectionMethod(EmployeeService::class, 'createOrUpdateSystemUser');
        $method->setAccessible(true);

        // Case 1: Create new user (userId = null)
        $createData = [
            'name' => 'Akun Baru',
            'email' => 'akunbaru@example.com',
            'password' => 'pass123',
            'role' => UserRole::WORKER->value,
        ];
        $userId = $method->invoke($this->service, null, $createData);
        $this->assertIsInt($userId);
        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'akunbaru@example.com']);

        // Case 2: Update existing user
        $updateData = [
            'name' => 'Akun Baru Diedit',
            'email' => 'diedit@example.com',
            'role' => UserRole::TREASURER->value,
            'password' => 'newpass456',
        ];
        $updatedId = $method->invoke($this->service, $userId, $updateData);
        $this->assertSame($userId, $updatedId);
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Akun Baru Diedit',
            'email' => 'diedit@example.com',
            'role' => UserRole::TREASURER->value,
        ]);
    }

    public function test_remove_system_user(): void
    {
        $method = new ReflectionMethod(EmployeeService::class, 'removeSystemUser');
        $method->setAccessible(true);

        $user = User::factory()->create(['role' => UserRole::WORKER->value]);
        $field = Field::factory()->create();
        FieldWorker::create(['fk_field_id' => $field->id, 'fk_user_id' => $user->id]);

        $method->invoke($this->service, $user->id);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('field_workers', ['fk_user_id' => $user->id]);

        // Removing null does not throw error
        $method->invoke($this->service, null);
    }

    public function test_sync_field_workers(): void
    {
        $method = new ReflectionMethod(EmployeeService::class, 'syncFieldWorkers');
        $method->setAccessible(true);

        $user = User::factory()->create(['role' => UserRole::WORKER->value]);
        $field1 = Field::factory()->create();
        $field2 = Field::factory()->create();

        // 1. Sync for worker with fields
        $method->invoke($this->service, $user->id, UserRole::WORKER->value, [$field1->id, $field2->id]);
        $this->assertSame(2, FieldWorker::where('fk_user_id', $user->id)->count());

        // 2. Sync with empty fields
        $method->invoke($this->service, $user->id, UserRole::WORKER->value, []);
        $this->assertSame(0, FieldWorker::where('fk_user_id', $user->id)->count());

        // 3. Sync with non-worker role (e.g. owner) does not create field workers
        $method->invoke($this->service, $user->id, UserRole::OWNER->value, [$field1->id]);
        $this->assertSame(0, FieldWorker::where('fk_user_id', $user->id)->count());
    }
}
