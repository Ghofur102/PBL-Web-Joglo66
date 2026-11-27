<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\UserRole;
use App\Services\Admin\ExpenseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Traits\UsesInMemoryDatabase;
use Tests\Traits\CreatesExpenseRecords;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class ExpenseServiceTest extends TestCase
{
    use UsesInMemoryDatabase, CreatesExpenseRecords;

    private const DATE_20260101 = '2026-01-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();
        $this->createExpensesTable();

        Schema::connection('mysql_joglo66_app')->create('field_admins', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id');
            $table->unsignedBigInteger('fk_field_id');
        });
    }

    public function test_get_expenses(): void
    {
        $this->createExpenseRecord([
            'name' => 'Older',
            'category' => 'Maintenance',
            'quantity' => 2,
            'unit_price' => 100,
            'expense_date' => self::DATE_20260101,
        ]);
        $this->createExpenseRecord([
            'fk_field_id' => 2,
            'name' => 'Latest',
            'category' => 'Utilities',
            'quantity' => 1,
            'unit_price' => 250,
            'expense_date' => '2026-02-01',
            'generate_at' => '2026-02-01',
        ]);

        $expenses = (new ExpenseService)->getExpenses([1]);

        $this->assertCount(1, $expenses);
        $this->assertSame('Older', $expenses->first()['name']);
        $this->assertSame(200, $expenses->first()['amount']);
        $this->assertSame('-', $expenses->first()['note']);
    }

    public function test_get_unique_categories(): void
    {
        foreach ([[1, 'Maintenance'], [1, 'Utilities'], [2, 'Cleaning']] as [$fieldId, $category]) {
            $this->createExpenseRecord([
                'fk_field_id' => $fieldId,
                'name' => $category,
                'category' => $category,
                'expense_date' => self::DATE_20260101,
            ]);
        }

        $this->assertEqualsCanonicalizing(
            ['Maintenance', 'Utilities'],
            (new ExpenseService)->getUniqueCategories([1])
        );
    }

    public function test_create_expense(): void
    {
        DB::connection('mysql_joglo66_app')->table('field_admins')->insert([
            'fk_user_id' => 8,
            'fk_field_id' => 4,
        ]);
        $worker = (object) ['id' => 8, 'role' => UserRole::WORKER->value];

        $expense = (new ExpenseService)->createExpense([
            'name' => 'Net repair',
            'category' => 'Maintenance',
            'quantity' => 2,
            'unit_price' => 150,
            'date' => '2026-04-10',
        ], $worker);

        $this->assertSame(4, $expense->fk_field_id);
        $this->assertSame(8, $expense->fk_user_id);
        $this->assertSame(300, $expense->amount);
        $this->assertNull($expense->proof_photo);
    }

    public function test_update_expense(): void
    {
        $expense = $this->createExpenseRecord([
            'name' => 'Old name',
            'category' => 'Maintenance',
        ]);

        $updated = (new ExpenseService)->updateExpense($expense->id, [
            'name' => 'New name',
            'category' => 'Utilities',
            'quantity' => 3,
            'unit_price' => 200,
            'date' => '2026-02-02',
            'note' => 'Updated',
        ]);

        $this->assertTrue($updated);
        $this->assertSame('New name', $expense->fresh()->name);
        $this->assertSame('Updated', $expense->fresh()->note);
        $this->assertFalse((new ExpenseService)->updateExpense(999, []));
    }

    public function test_delete_expense(): void
    {
        $expense = $this->createExpenseRecord([
            'name' => 'Disposable',
            'unit_price' => 50,
        ]);

        $service = new ExpenseService;

        $this->assertTrue($service->deleteExpense($expense->id));
        $this->assertNull($expense->fresh());
        $this->assertFalse($service->deleteExpense($expense->id));
    }
}
