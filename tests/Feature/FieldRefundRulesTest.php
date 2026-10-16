<?php

namespace Tests\Feature;

use App\Models\Field;
use App\Models\User;
use App\Services\RefundRuleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class FieldRefundRulesTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDatabasePath = database_path('refund_rules_test.sqlite');
        if (file_exists($this->testDatabasePath)) {
            unlink($this->testDatabasePath);
        }
        touch($this->testDatabasePath);

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = $this->testDatabasePath;
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => $sqlite,
            'database.connections.mysql_joglo66_app' => $sqlite,
        ]);
        DB::purge('sqlite');
        DB::purge('mysql_joglo66_app');

        $this->artisan('migrate:fresh', ['--database' => 'sqlite', '--force' => true])
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::disconnect('mysql_joglo66_app');
        if (isset($this->testDatabasePath) && file_exists($this->testDatabasePath)) {
            unlink($this->testDatabasePath);
        }

        parent::tearDown();
    }

    public function test_owner_creates_field_with_default_refund_rules(): void
    {
        $owner = $this->createUser('owner');

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/owner/fields', [
            'name' => 'Lapangan A',
            'category' => 'futsal',
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.refund_rules');

        $this->assertDatabaseHas('field_refund_rules', [
            'fk_field_id' => $response->json('data.id'),
            'rule_type' => 'cancel',
            'min_hours_before' => 72,
            'refund_percent' => 100,
        ], 'mysql_joglo66_app');
        $this->assertDatabaseHas('field_refund_rules', [
            'fk_field_id' => $response->json('data.id'),
            'rule_type' => 'reschedule',
            'min_hours_before' => 72,
            'refund_percent' => 100,
        ], 'mysql_joglo66_app');
    }

    public function test_migration_backfills_existing_fields_and_rolls_back(): void
    {
        $owner = $this->createUser('owner');
        $field = $this->createField($owner);
        Schema::dropIfExists('field_refund_rules');

        $migration = require database_path('migrations/2026_10_16_000000_create_field_refund_rules_table.php');
        $migration->up();

        $this->assertDatabaseCount('field_refund_rules', 2, 'mysql_joglo66_app');
        $this->assertDatabaseHas('field_refund_rules', [
            'fk_field_id' => $field->id,
            'rule_type' => 'cancel',
            'min_hours_before' => 72,
            'refund_percent' => 100,
        ], 'mysql_joglo66_app');

        $migration->down();
        $this->assertFalse(Schema::hasTable('field_refund_rules'));
    }

    public function test_owner_updates_rules_and_resolver_selects_highest_matching_threshold(): void
    {
        $owner = $this->createUser('owner');
        $field = $this->createField($owner);
        $rules = [
            ['rule_type' => 'cancel', 'min_hours_before' => 24, 'refund_percent' => 25],
            ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100],
            ['rule_type' => 'reschedule', 'min_hours_before' => 48, 'refund_percent' => 50],
        ];

        $this->actingAs($owner, 'sanctum')->postJson("/api/owner/fields/{$field->id}/update", [
            'name' => $field->name,
            'category' => $field->category,
            'refund_rules' => $rules,
        ])->assertOk();

        $service = app(RefundRuleService::class);
        $this->assertSame(25, $service->resolvePercent($field->id, 'cancel', 30));
        $this->assertSame(100, $service->resolvePercent($field->id, 'cancel', 72));
        $this->assertNull($service->resolvePercent($field->id, 'reschedule', 24));
    }

    public function test_invalid_or_incomplete_refund_rules_are_rejected(): void
    {
        $owner = $this->createUser('owner');
        $payload = [
            'name' => 'Lapangan A',
            'category' => 'futsal',
            'refund_rules' => [
                ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 101],
                ['rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 100],
            ],
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/fields', $payload)
            ->assertUnprocessable();

        $payload['refund_rules'][0]['refund_percent'] = -1;
        $this->postJson('/api/owner/fields', $payload)->assertUnprocessable();

        $payload['refund_rules'] = [
            ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100],
        ];
        $this->postJson('/api/owner/fields', $payload)->assertUnprocessable();
    }

    public function test_non_owner_cannot_create_field(): void
    {
        $tenant = $this->createUser('tenant');

        $this->actingAs($tenant, 'sanctum')
            ->postJson('/api/owner/fields', [
                'name' => 'Lapangan A',
                'category' => 'futsal',
            ])
            ->assertForbidden();
    }

    public function test_owner_cannot_update_another_owners_field(): void
    {
        $owner = $this->createUser('owner');
        $otherOwner = $this->createUser('owner');
        $field = $this->createField($otherOwner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/fields/{$field->id}/update", [
                'name' => 'Lapangan diubah',
                'category' => 'futsal',
            ])
            ->assertNotFound();
    }

    public function test_assigned_admin_can_update_refund_rules(): void
    {
        $owner = $this->createUser('owner');
        $admin = $this->createUser('worker');
        $field = $this->createField($owner);
        DB::table('field_workers')->insert([
            'fk_field_id' => $field->id,
            'fk_user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/update-field', [
            'id' => $field->id,
            'refund_rules' => [
                ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 80],
                ['rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 60],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('field_refund_rules', [
            'fk_field_id' => $field->id,
            'rule_type' => 'cancel',
            'refund_percent' => 80,
        ], 'mysql_joglo66_app');
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.uniqid().'@example.test',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function createField(User $owner): Field
    {
        return Field::query()->create([
            'name' => 'Lapangan A',
            'category' => 'futsal',
            'fk_user_id' => $owner->id,
        ]);
    }
}
