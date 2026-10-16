<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_refund_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_field_id')->constrained('fields')->cascadeOnDelete();
            $table->enum('rule_type', ['cancel', 'reschedule']);
            $table->unsignedInteger('min_hours_before');
            $table->unsignedTinyInteger('refund_percent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['fk_field_id', 'rule_type', 'min_hours_before'], 'field_refund_rules_field_type_hours_unique');
            $table->index(['fk_field_id', 'rule_type'], 'field_refund_rules_field_type_index');
        });

        DB::table('fields')->select('id')->orderBy('id')->chunkById(500, function ($fields): void {
            $now = now();
            $rules = [];

            foreach ($fields as $field) {
                foreach (['cancel', 'reschedule'] as $type) {
                    $rules[] = [
                        'fk_field_id' => $field->id,
                        'rule_type' => $type,
                        'min_hours_before' => 72,
                        'refund_percent' => 100,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rules !== []) {
                DB::table('field_refund_rules')->insert($rules);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_refund_rules');
    }
};
