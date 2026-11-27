<?php

namespace Tests\Traits;

use App\Models\Expense;

trait CreatesExpenseRecords
{
    protected function createExpenseRecord(array $attributes = []): Expense
    {
        return Expense::create(array_replace([
            'fk_field_id' => 1,
            'fk_user_id' => 1,
            'name' => 'Test expense',
            'category' => 'Other',
            'quantity' => 1,
            'unit_price' => 100,
            'expense_date' => '2026-01-01',
            'generate_at' => '2026-01-01',
        ], $attributes));
    }
}
