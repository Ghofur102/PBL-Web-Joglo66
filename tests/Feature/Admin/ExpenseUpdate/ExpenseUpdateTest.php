<?php

namespace Tests\Feature\Admin\ExpenseUpdate;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpenseUpdateTest extends TestCase
{
    private const CONNECTION = 'mysql_joglo66_app';

    protected function setUp(): void
    {   
        parent::setUp();

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = ':memory:';

        config([
            'database.default' => self::CONNECTION,
            'database.connections.'.self::CONNECTION => $sqlite,
        ]);
        DB::purge(self::CONNECTION);

        Schema::connection(self::CONNECTION)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });

        Schema::connection(self::CONNECTION)->create('field_workers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id');
            $table->unsignedBigInteger('fk_field_id');
            $table->timestamps();
        });

        Schema::connection(self::CONNECTION)->create('expenses', function (Blueprint $table): void {
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
    }

    public function test_tc_adm_16_1_updates_expense_for_assigned_field(): void
    {
        $worker = $this->createAssignedWorker();
        $expense = $this->createExpense();

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/update-expense/'.$expense->id, [
                'name' => 'Beli bola',
                'category' => 'Perlengkapan',
                'quantity' => 2,
                'unit_price' => 100000,
                'date' => '2026-10-25',
                'note' => 'Bola untuk latihan',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'fk_field_id' => 1,
            'name' => 'Beli bola',
            'category' => 'Perlengkapan',
            'quantity' => 2,
            'unit_price' => 100000,
            'expense_date' => '2026-10-25',
            'note' => 'Bola untuk latihan',
        ], self::CONNECTION);
    }

    public function test_tc_adm_16_2_rejects_negative_amount_and_keeps_expense_unchanged(): void
    {
        $worker = $this->createAssignedWorker();
        $expense = $this->createExpense();

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/update-expense/'.$expense->id, [
                ...$this->validPayload(),
                'unit_price' => -1000,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'name' => 'Pengeluaran awal',
            'quantity' => 1,
            'unit_price' => 100000,
            'note' => 'Catatan awal',
        ], self::CONNECTION);
    }

    public function test_tc_adm_16_3_rejects_expense_from_unassigned_field_and_keeps_it_unchanged(): void
    {
        $worker = $this->createAssignedWorker();
        $expense = $this->createExpense(fieldId: 2);

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/update-expense/'.$expense->id, [
                ...$this->validPayload(),
                'name' => 'Tidak boleh berubah',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'fk_field_id' => 2,
            'name' => 'Pengeluaran awal',
            'unit_price' => 100000,
        ], self::CONNECTION);
    }

    public function test_tc_adm_16_4_rejects_script_in_expense_description_and_keeps_it_unchanged(): void
    {
        $worker = $this->createAssignedWorker();
        $expense = $this->createExpense();

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/update-expense/'.$expense->id, [
                ...$this->validPayload(),
                'name' => '<script>alert(1)</script>',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'name' => 'Pengeluaran awal',
            'note' => 'Catatan awal',
        ], self::CONNECTION);
    }

    private function createAssignedWorker(): User
    {
        $worker = User::create([
            'name' => 'Admin Lapangan',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'worker',
        ]);

        DB::connection(self::CONNECTION)->table('field_workers')->insert([
            'fk_user_id' => $worker->id,
            'fk_field_id' => 1,
        ]);

        return $worker;
    }

    private function createExpense(int $fieldId = 1): Expense
    {
        return Expense::create([
            'fk_field_id' => $fieldId,
            'fk_user_id' => 1,
            'name' => 'Pengeluaran awal',
            'category' => 'Operasional',
            'quantity' => 1,
            'unit_price' => 100000,
            'expense_date' => '2026-10-01',
            'note' => 'Catatan awal',
            'generate_at' => '2026-10-01',
        ]);
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Nama baru',
            'category' => 'Operasional',
            'quantity' => 1,
            'unit_price' => 200000,
            'date' => '2026-10-25',
            'note' => 'Catatan baru',
        ];
    }
}
