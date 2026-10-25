<?php

namespace Tests\Unit\Services\Treasure;

use App\Enums\GeneralStatus;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Services\Treasure\SalaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class SalaryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.mysql_joglo66_app' => $connection,
        ]);
        DB::purge('mysql_joglo66_app');

        Schema::connection('mysql_joglo66_app')->create('fields', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });
        Schema::connection('mysql_joglo66_app')->create('employees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id')->nullable();
            $table->string('name');
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            $table->string('position');
            $table->unsignedInteger('base_salary');
            $table->date('join_date');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->unsignedBigInteger('fk_user_id');
            $table->string('name');
            $table->string('category');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->date('expense_date');
            $table->string('proof_photo')->nullable();
            $table->text('note')->nullable();
            $table->date('generate_at');
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('employee_salaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_employee_id');
            $table->unsignedBigInteger('fk_expense_id');
            $table->unsignedInteger('amount_paid');
            $table->string('period_month');
            $table->unsignedInteger('period_year');
            $table->date('payment_date');
            $table->unsignedInteger('bonus')->default(0);
            $table->unsignedInteger('deduction')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_get_salary_list(): void
    {
        $activeEmployee = $this->createEmployee('Active', 500000, GeneralStatus::ACTIVE->value);
        $this->createEmployee('Inactive', 300000, 'inactive');
        Expense::create([
            'fk_field_id' => 1,
            'fk_user_id' => 1,
            'name' => 'Salary',
            'category' => 'Gaji',
            'quantity' => 1,
            'unit_price' => 600000,
            'expense_date' => '2026-10-01',
            'generate_at' => '2026-10-01',
        ]);
        EmployeeSalary::create([
            'fk_employee_id' => $activeEmployee->id,
            'fk_expense_id' => 1,
            'amount_paid' => 550000,
            'period_month' => strtolower(date('F')),
            'period_year' => (int) date('Y'),
            'payment_date' => date('Y-m-d'),
            'bonus' => 50000,
            'deduction' => 0,
            'notes' => 'Paid',
        ]);

        $salaries = (new SalaryService)->getSalaryList((int) date('n'), (int) date('Y'));

        $this->assertCount(1, $salaries);
        $this->assertSame('Active', $salaries->first()['name']);
        $this->assertTrue($salaries->first()['is_edited']);
        $this->assertSame(550000, $salaries->first()['amount_paid']);
        $this->assertSame(50000, $salaries->first()['bonus']);
    }

    public function test_update_or_calculate_salary(): void
    {
        $employee = $this->createEmployee('Employee', 500000, GeneralStatus::ACTIVE->value);
        Expense::create([
            'fk_field_id' => 1,
            'fk_user_id' => 3,
            'name' => 'Salary',
            'category' => 'Gaji',
            'quantity' => 1,
            'unit_price' => 400000,
            'expense_date' => '2026-01-01',
            'generate_at' => '2026-01-01',
        ]);
        $expense = Expense::first();
        $salary = EmployeeSalary::create([
            'fk_employee_id' => $employee->id,
            'fk_expense_id' => $expense->id,
            'amount_paid' => 400000,
            'period_month' => 'january',
            'period_year' => 2026,
            'payment_date' => '2026-01-01',
            'bonus' => 0,
            'deduction' => 0,
            'notes' => '-',
        ]);

        (new SalaryService)->updateOrCalculateSalary([
            'employee_id' => $employee->id,
            'bulan' => 1,
            'tahun' => 2026,
            'amount_paid' => 500000,
            'bonus' => 25000,
            'deduction' => 10000,
            'notes' => 'Updated',
        ], 3);

        $this->assertSame(515000, $salary->fresh()->amount_paid + $salary->fresh()->bonus - $salary->fresh()->deduction);
        $this->assertSame('Updated', $salary->fresh()->notes);
        $this->assertSame(515000, $expense->fresh()->amount);
    }

    public function test_sync_monthly_salaries(): void
    {
        $this->createEmployee('One', 300000, GeneralStatus::ACTIVE->value);
        $this->createEmployee('Two', 400000, GeneralStatus::ACTIVE->value);
        $this->createEmployee('Inactive', 200000, 'inactive');

        $synced = (new SalaryService)->syncMonthlySalaries([
            'bulan' => 1,
            'tahun' => 2026,
        ], 3);

        $this->assertSame(2, $synced);
        $this->assertSame(2, EmployeeSalary::where('period_month', 'january')->count());
        $this->assertDatabaseHas('expenses', ['category' => 'Gaji', 'amount' => 300000], 'mysql_joglo66_app');
    }

    public function test_validate_not_future_date(): void
    {
        $method = new \ReflectionMethod(SalaryService::class, 'validateNotFutureDate');
        $service = new SalaryService;
        $method->invoke($service, (int) date('n'), (int) date('Y'));

        $this->expectException(HttpException::class);
        $method->invoke($service, (int) date('n') === 12 ? 1 : (int) date('n') + 1, (int) date('Y'));
    }

    private function createEmployee(string $name, int $salary, string $status): Employee
    {
        return Employee::create([
            'name' => $name,
            'position' => 'Worker',
            'base_salary' => $salary,
            'join_date' => '2026-01-01',
            'status' => $status,
        ]);
    }
}
