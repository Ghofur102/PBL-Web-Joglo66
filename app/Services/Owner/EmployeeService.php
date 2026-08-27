<?php

namespace App\Services\Owner;

use App\Models\Employee;
use App\Models\User;
use App\Models\EmployeeSalary;
use App\Models\FieldWorker;
use App\Enums\GeneralStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EmployeeService
{
    public function getEmployeesList(): array
    {
        return Employee::query()->with(['user.fieldWorker.field'])->get()->map(function ($emp) {
            $fieldIds = [];
            $fieldNames = [];

            if ($emp->user && $emp->user->fieldWorker) {
                foreach ($emp->user->fieldWorker as $fw) {
                    if ($fw->field) {
                        $fieldIds[] = (int) $fw->field->id;
                        $fieldNames[] = $fw->field->name;
                    }
                }
            }

            return [
                'id'           => $emp->id,
                'user_id'      => $emp->fk_user_id,
                'name'         => $emp->name,
                'email'        => $emp->user->email ?? null,
                'role'         => $emp->user->role ?? null,
                'phone_number' => $emp->phone_number,
                'address'      => $emp->address,
                'position'     => $emp->position,
                'base_salary'  => $emp->base_salary,
                'join_date'    => $emp->join_date ?? now()->toDateString(),
                'status'       => $emp->status,
                'is_system'    => $emp->fk_user_id !== null,
                'field_ids'    => $fieldIds,
                'field_names'  => implode(', ', $fieldNames),
            ];
        })->toArray();
    }

    public function storeEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $userId = null;
            $isSystem = filter_var($data['is_system'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($isSystem) {
                $user = User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role'     => $data['role'],
                ]);
                $userId = $user->id;

                $this->syncFieldWorkers($userId, $data['role'], $data['field_ids'] ?? []);
            }

            return Employee::create([
                'fk_user_id'   => $userId,
                'name'         => $data['name'],
                'phone_number' => $data['phone_number'] ?? null,
                'address'      => $data['address'] ?? null,
                'position'     => $data['position'],
                'base_salary'  => $data['base_salary'],
                'join_date'    => $data['join_date'] ?? now()->toDateString(),
                'status'       => GeneralStatus::ACTIVE->value,
            ]);
        });
    }

    public function updateEmployee(Employee $employee, array $data): void
    {
        DB::transaction(function () use ($employee, $data) {
            $isSystem = filter_var($data['is_system'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $userId = $this->syncUserAccount($employee->fk_user_id, $isSystem, $data);

            if ($isSystem && $userId) {
                $this->syncFieldWorkers($userId, $data['role'], $data['field_ids'] ?? []);
            }

            $employee->update([
                'fk_user_id'   => $userId,
                'name'         => $data['name'],
                'phone_number' => $data['phone_number'] ?? null,
                'address'      => $data['address'] ?? null,
                'position'     => $data['position'],
                'base_salary'  => $data['base_salary'],
                'status'       => $data['status'] ?? GeneralStatus::ACTIVE->value,
            ]);
        });
    }

    public function destroyEmployee(Employee $employee): void
    {
        $hasSalaryRecords = EmployeeSalary::where('fk_employee_id', $employee->id)->exists();
        if ($hasSalaryRecords) {
            throw new ConflictHttpException('Karyawan tidak dapat dihapus karena memiliki riwayat penggajian.');
        }

        DB::transaction(function () use ($employee) {
            $userId = $employee->fk_user_id;
            if ($userId) {
                FieldWorker::where('fk_user_id', $userId)->delete();
            }
            $employee->delete();
            if ($userId) {
                User::where('id', $userId)->delete();
            }
        });
    }

    private function syncUserAccount(?int $userId, bool $isSystem, array $data): ?int
    {
        if ($isSystem) {
            return $this->createOrUpdateSystemUser($userId, $data);
        }

        $this->removeSystemUser($userId);
        return null;
    }

    private function createOrUpdateSystemUser(?int $userId, array $data): int
    {
        if (!$userId) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role'     => $data['role'],
            ]);
            return $user->id;
        }

        $user = User::find($userId);
        if ($user) {
            $userData = [
                'name'  => $data['name'],
                'email' => $data['email'],
                'role'  => $data['role'],
            ];
            if (!empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }
            $user->update($userData);
        }

        return $userId;
    }

    private function removeSystemUser(?int $userId): void
    {
        if ($userId) {
            FieldWorker::where('fk_user_id', $userId)->delete();
            User::where('id', $userId)->delete();
        }
    }

    private function syncFieldWorkers(int $userId, string $role, array $fieldIds): void
    {
        FieldWorker::where('fk_user_id', $userId)->delete();

        if (in_array($role, ['worker', 'treasurer'], true) && !empty($fieldIds)) {
            foreach ($fieldIds as $fId) {
                FieldWorker::create([
                    'fk_field_id' => (int) $fId,
                    'fk_user_id'  => $userId,
                ]);
            }
        }
    }
}
