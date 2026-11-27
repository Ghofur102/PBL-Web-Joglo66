<?php

namespace Tests\Unit\Services;

use App\Models\Field;
use App\Models\FieldRefundRule;
use App\Services\RefundRuleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class RefundRuleServiceTest extends TestCase
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
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('field_refund_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->string('rule_type');
            $table->unsignedInteger('min_hours_before');
            $table->unsignedTinyInteger('refund_percent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_default_rules(): void
    {
        $this->assertSame([
            ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => true],
            ['rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => true],
        ], RefundRuleService::defaultRules());
    }

    public function test_sync_rules(): void
    {
        $field = Field::create(['name' => 'Main field']);
        $field->refundRules()->create([
            'rule_type' => 'cancel',
            'min_hours_before' => 24,
            'refund_percent' => 50,
            'is_active' => true,
        ]);
        $rules = [
            ['rule_type' => 'cancel', 'min_hours_before' => 48, 'refund_percent' => 75],
            ['rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 80],
        ];

        (new RefundRuleService)->syncRules($field, $rules);

        $this->assertSame(2, $field->refundRules()->count());
        $this->assertDatabaseHas('field_refund_rules', [
            'fk_field_id' => $field->id,
            'rule_type' => 'cancel',
            'min_hours_before' => 48,
            'refund_percent' => 75,
        ], 'mysql_joglo66_app');
        $this->assertDatabaseMissing('field_refund_rules', [
            'fk_field_id' => $field->id,
            'min_hours_before' => 24,
        ], 'mysql_joglo66_app');
    }

    public function test_resolve_percent(): void
    {
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 24, 'refund_percent' => 50, 'is_active' => true]);
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => true]);

        $service = new RefundRuleService;

        $this->assertSame(100, $service->resolvePercent(1, 'cancel', 72));
        $this->assertSame(50, $service->resolvePercent(1, 'cancel', 48));
        $this->assertNull($service->resolvePercent(1, 'cancel', 12));
    }

    public function test_get_terms(): void
    {
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'reschedule', 'min_hours_before' => 24, 'refund_percent' => 40, 'is_active' => true]);
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 90, 'is_active' => true]);

        $terms = (new RefundRuleService)->getTerms(1, 'reschedule');

        $this->assertSame([72, 24], array_column($terms, 'min_hours_before'));
        $this->assertStringContainsString('90%', $terms[0]['text']);
        $this->assertStringContainsString('selisih harga', $terms[0]['text']);
    }

    public function test_get_policy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00'));
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 24, 'refund_percent' => 60, 'is_active' => true]);
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => true]);

        $policy = (new RefundRuleService)->getPolicy(1, 'cancel', Carbon::parse('2026-10-04 12:00:00'));

        $this->assertTrue($policy['isWithinWindow']);
        $this->assertSame(100, $policy['currentPercent']);
        $this->assertSame('2026-10-03 12:00:00', $policy['deadline']->toDateTimeString());
        $this->assertCount(2, $policy['terms']);
        Carbon::setTestNow();
    }

    public function test_active_rules(): void
    {
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 24, 'refund_percent' => 40, 'is_active' => true]);
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => false]);

        $terms = (new RefundRuleService)->getTerms(1, 'cancel');

        $this->assertCount(1, $terms);
        $this->assertSame(24, $terms[0]['min_hours_before']);
    }

    public function test_format_term(): void
    {
        FieldRefundRule::create(['fk_field_id' => 1, 'rule_type' => 'cancel', 'min_hours_before' => 48, 'refund_percent' => 75, 'is_active' => true]);

        $terms = (new RefundRuleService)->getTerms(1, 'cancel');

        $this->assertSame(
            'Pembatalan diajukan minimal 48 jam sebelum waktu main: kebijakan refund 75% dari total pembayaran. Keputusan dan nominal akhir ditetapkan admin.',
            $terms[0]['text']
        );
    }
}
