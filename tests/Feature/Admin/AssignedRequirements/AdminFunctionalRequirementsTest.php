<?php

namespace Tests\Feature\Admin\AssignedRequirements;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminFunctionalRequirementsTest extends TestCase
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

        $schema = Schema::connection(self::CONNECTION);
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });
        $schema->create('employees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id');
            $table->string('name');
            $table->string('position');
            $table->unsignedInteger('base_salary')->default(0);
            $table->date('join_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        $schema->create('fields', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('fk_user_id')->nullable();
            $table->string('category')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
        $schema->create('field_workers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id');
            $table->unsignedBigInteger('fk_field_id');
            $table->timestamps();
        });
        $schema->create('field_closures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->time('field_closure_start_time');
            $table->time('field_closure_end_time');
        });
        $schema->create('field_admins', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id');
            $table->unsignedBigInteger('fk_field_id');
        });
        $schema->create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->string('name');
            $table->string('type')->default('lainnya');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('price_hour')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
        $schema->create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id')->nullable();
            $table->unsignedBigInteger('fk_field_id');
            $table->string('team_name')->nullable();
            $table->date('booking_date')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $schema->create('booking_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_id');
            $table->date('play_date');
            $table->time('start_play_time');
            $table->time('end_play_time');
            $table->integer('price');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('booking_attributes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_detail_id');
            $table->unsignedBigInteger('fk_attribute_id');
            $table->unsignedInteger('quantity');
            $table->integer('price');
            $table->integer('total');
            $table->date('transaction_date');
            $table->string('status');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->unsignedInteger('duration_hours');
            $table->timestamps();
        });
        $schema->create('expenses', function (Blueprint $table): void {
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
        $schema->create('booking_reschedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_detail_id');
            $table->unsignedBigInteger('fk_field_closure_id')->nullable();
            $table->date('old_date');
            $table->string('status_refund');
            $table->string('approval_status')->default('approved');
            $table->string('sender_by')->default('admin');
            $table->text('rejection_reason')->nullable();
            $table->date('new_play_date')->nullable();
            $table->time('new_start_play_time')->nullable();
            $table->time('new_end_play_time')->nullable();
            $table->integer('new_price')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        $schema->create('booking_cancelled', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_detail_id');
            $table->unsignedBigInteger('fk_field_closure_id')->nullable();
            $table->date('cancle_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('status_refund')->default('None');
            $table->string('approval_status')->default('pending');
            $table->string('sender_by')->default('tenant');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
        $schema->create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_id');
            $table->unsignedBigInteger('fk_booking_detail_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('payment_url')->nullable();
            $table->string('payment_type');
            $table->string('method');
            $table->integer('amount');
            $table->string('status');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
        $schema->create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Storage::fake('public');
        $this->createField();
    }

    public function test_tc_adm_13_1_updates_attribute_on_assigned_field(): void
    {
        $worker = $this->createWorker();
        $attributeId = $this->createAttribute();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/update-attribute/'.$attributeId, [
                'name' => 'Rompi Tim',
                'stock' => 10,
                'price_hour' => 5000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attributes', [
            'id' => $attributeId,
            'name' => 'Rompi Tim',
            'stock' => 10,
            'price_hour' => 5000,
        ], self::CONNECTION);
    }

    public function test_tc_adm_14_1_rents_attribute_and_decrements_stock(): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail();
        $attributeId = $this->createAttribute(stock: 10, price: 5000);

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/rent-attribute', [
                'fk_booking_detail_id' => $detailId,
                'items' => [['fk_attribute_id' => $attributeId, 'quantity' => 2]],
                'customer_name' => 'Tim Uji',
                'duration_hours' => 2,
                'transaction_date' => '2026-10-16',
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_price', 20000);

        $this->assertDatabaseHas('attributes', ['id' => $attributeId, 'stock' => 8], self::CONNECTION);
        $this->assertDatabaseHas('booking_attributes', [
            'fk_booking_detail_id' => $detailId,
            'fk_attribute_id' => $attributeId,
            'total' => 20000,
        ], self::CONNECTION);
    }

    public function test_tc_adm_15_1_creates_expense_with_valid_receipt(): void
    {
        $worker = $this->createWorker();
        DB::connection(self::CONNECTION)->table('field_admins')->insert([
            'fk_user_id' => $worker->id,
            'fk_field_id' => 1,
        ]);

        $this->actingAs($worker, 'sanctum')
            ->post('/api/admin/create-expense', [
                'name' => 'Beli net',
                'category' => 'Operasional',
                'quantity' => 1,
                'unit_price' => 150000,
                'date' => '2026-10-16',
                'image' => UploadedFile::fake()->image('nota.png')->size(1024),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('expenses', [
            'fk_field_id' => 1,
            'fk_user_id' => $worker->id,
            'name' => 'Beli net',
            'unit_price' => 150000,
        ], self::CONNECTION);
        $this->assertCount(1, Storage::disk('public')->allFiles('expenses'));
    }

    public function test_tc_adm_17_1_reschedules_booking_to_available_slot(): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId, [
                'new_play_date' => '2026-10-16',
                'new_start_time' => '10:00',
                'new_end_time' => '11:00',
                'reason' => 'Penyesuaian jadwal',
                'new_price' => 100000,
                'financial_action' => 'Settle_Later',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('booking_details', [
            'id' => $detailId,
            'play_date' => '2026-10-16',
            'start_play_time' => '10:00',
        ], self::CONNECTION);
    }

    public function test_tc_adm_18_1_cancels_booking_and_records_refund(): void
    {
        $worker = $this->createWorker();
        $tenant = $this->createTenant();
        $detailId = $this->createBookingDetail(status: 'active', tenantId: $tenant->id);
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);
        Notification::fake();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId, [
                'reason' => 'Permintaan admin',
                'status_refund' => 'Full',
                'refund_amount' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('booking_details', ['id' => $detailId, 'status' => 'cancelled'], self::CONNECTION);
        $this->assertDatabaseHas('payments', [
            'fk_booking_detail_id' => $detailId,
            'payment_type' => 'refund',
            'amount' => 100000,
        ], self::CONNECTION);
        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_tc_adm_19_1_calculates_custom_cancellation_refund_percentage(): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail(status: 'active');
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId, [
                'reason' => 'Pembatalan',
                'status_refund' => 'Partial',
                'refund_percent' => 50,
                'refund_amount' => 50,
            ])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'fk_booking_detail_id' => $detailId,
            'payment_type' => 'refund',
            'amount' => 50000,
        ], self::CONNECTION);
    }

    public function test_tc_adm_20_1_calculates_custom_refund_for_cheaper_reschedule(): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail(price: 100000);
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId, [
                'new_play_date' => '2026-10-16',
                'new_start_time' => '10:00',
                'new_end_time' => '11:00',
                'reason' => 'Slot lebih murah',
                'new_price' => 70000,
                'refund_percent' => 50,
                'financial_action' => 'Settle_Later',
            ])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'fk_booking_detail_id' => $detailId,
            'payment_type' => 'refund',
            'amount' => 15000,
        ], self::CONNECTION);
    }

    public function test_tc_adm_21_1_marks_more_expensive_reschedule_as_underpaid(): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail(price: 100000);
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId, [
                'new_play_date' => '2026-10-16',
                'new_start_time' => '10:00',
                'new_end_time' => '11:00',
                'reason' => 'Slot lebih mahal',
                'new_price' => 130000,
                'financial_action' => 'Settle_Later',
            ])
            ->assertOk();

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/admin/detail-booking/'.$detailId)
            ->assertOk()
            ->assertJsonPath('data.sessions.0.total_paid', 100000)
            ->assertJsonPath('data.sessions.0.remaining_payment', 30000);
    }

    public function test_tc_adm_22_1_marks_pending_refund_as_paid(): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail(price: 100000);
        $bookingId = DB::connection(self::CONNECTION)->table('booking_details')->where('id', $detailId)->value('fk_booking_id');
        DB::connection(self::CONNECTION)->table('payments')->insert([
            'fk_booking_id' => $bookingId,
            'fk_booking_detail_id' => $detailId,
            'reference_id' => 'PAY-1',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 150000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        $pendingRefundId = DB::connection(self::CONNECTION)->table('payments')->insertGetId([
            'fk_booking_id' => $bookingId,
            'fk_booking_detail_id' => $detailId,
            'reference_id' => 'REF-1',
            'payment_type' => PaymentType::REFUND->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::PENDING->value,
        ]);

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/refund-overpayment/'.$detailId)
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $pendingRefundId,
            'status' => 'success',
        ], self::CONNECTION);
    }

    public function test_tc_adm_23_1_approves_reschedule_request(): void
    {
        $worker = $this->createWorker();
        $tenant = $this->createTenant();
        $detailId = $this->createBookingDetail(status: 'active', tenantId: $tenant->id);
        $this->createReschedule($detailId);
        Notification::fake();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId.'/approve')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('booking_details', [
            'id' => $detailId,
            'play_date' => '2026-10-16',
            'status' => 'reschedule',
        ], self::CONNECTION);
        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_tc_adm_24_1_rejects_reschedule_request(): void
    {
        $worker = $this->createWorker();
        $tenant = $this->createTenant();
        $detailId = $this->createBookingDetail(status: 'active', tenantId: $tenant->id);
        $rescheduleId = $this->createReschedule($detailId);
        Notification::fake();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId.'/reject', [
                'rejection_reason' => 'Jadwal tidak tersedia',
            ])
            ->assertOk();

        $this->assertDatabaseHas('booking_reschedules', [
            'id' => $rescheduleId,
            'approval_status' => 'rejected',
        ], self::CONNECTION);
        $this->assertDatabaseHas('booking_details', [
            'id' => $detailId,
            'status' => 'active',
        ], self::CONNECTION);
        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_tc_adm_25_1_approves_cancellation_request_and_records_refund(): void
    {
        $worker = $this->createWorker();
        $tenant = $this->createTenant();
        $detailId = $this->createBookingDetail(status: 'active', tenantId: $tenant->id);
        $cancellationId = $this->createCancellation($detailId);
        Notification::fake();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId.'/approve', [
                'status_refund' => 'Partial',
                'refund_amount' => 50000,
            ])
            ->assertOk();

        $this->assertDatabaseHas('booking_details', ['id' => $detailId, 'status' => 'cancelled'], self::CONNECTION);
        $this->assertDatabaseHas('booking_cancelled', ['id' => $cancellationId, 'approval_status' => 'approved'], self::CONNECTION);
        $this->assertDatabaseHas('payments', ['fk_booking_detail_id' => $detailId, 'amount' => 50000], self::CONNECTION);
        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_tc_adm_26_1_rejects_cancellation_request_without_cancelling_booking(): void
    {
        $worker = $this->createWorker();
        $tenant = $this->createTenant();
        $detailId = $this->createBookingDetail(status: 'active', tenantId: $tenant->id);
        $cancellationId = $this->createCancellation($detailId);
        Notification::fake();

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId.'/reject', [
                'rejection_reason' => 'Jadwal tetap dipakai',
            ])
            ->assertOk();

        $this->assertDatabaseHas('booking_cancelled', [
            'id' => $cancellationId,
            'approval_status' => 'rejected',
        ], self::CONNECTION);
        $this->assertDatabaseHas('booking_details', ['id' => $detailId, 'status' => 'active'], self::CONNECTION);
        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_tc_adm_27_1_limits_monthly_report_to_assigned_field(): void
    {
        $worker = $this->createWorker();
        DB::connection(self::CONNECTION)->table('fields')->insert([
            'id' => 2,
            'name' => 'Lapangan Lain',
        ]);
        DB::connection(self::CONNECTION)->table('field_workers')->insert([
            'fk_user_id' => $worker->id,
            'fk_field_id' => 2,
        ]);
        $this->createExpenseRow(1, 100000);
        $this->createExpenseRow(2, 900000);

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/laporan-bulanan?month=10&year=2026&field_ids=1')
            ->assertOk()
            ->assertJsonPath('data.total_expense', 100000);
    }

    public function test_tc_adm_28_1_logs_in_admin_and_allows_admin_profile_access(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);

        $token = $login->json('token');
        $this->withToken($token)
            ->getJson('/api/admin/profile')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_tc_adm_29_1_rejects_unknown_login_email_without_issuing_token(): void
    {
        $this->postJson('/api/login', [
            'email' => 'unknown@example.test',
            'password' => 'incorrect',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('token');
    }

    #[DataProvider('attributeUpdateScenarios')]
    public function test_remaining_attribute_update_scenarios(
        string $caseId,
        array $payload,
        int $expectedStatus,
        int $attributeFieldId = 1,
        ?array $expectedChanges = null,
    ): void {
        $worker = $this->createWorker();
        $this->createField(2);
        $attributeId = $this->createAttribute(fieldId: $attributeFieldId);

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/update-attribute/'.$attributeId, $payload);
        if ($caseId === 'TC-ADM-13.6' && $response->status() === 200) {
            $this->assertDatabaseMissing('attributes', [
                'id' => $attributeId,
                'name' => '<script>alert(1)</script>',
            ], self::CONNECTION);
        } else {
            $response->assertStatus($expectedStatus);
        }

        if ($expectedChanges !== null) {
            $this->assertDatabaseHas('attributes', ['id' => $attributeId] + $expectedChanges, self::CONNECTION);
        } elseif ($expectedStatus !== 200) {
            $this->assertDatabaseHas('attributes', [
                'id' => $attributeId,
                'name' => 'Rompi',
                'stock' => 1,
                'price_hour' => 5000,
            ], self::CONNECTION);
        }
    }

    public static function attributeUpdateScenarios(): array
    {
        return [
            'TC-ADM-13.2' => ['TC-ADM-13.2', ['stock' => -1], 422],
            'TC-ADM-13.3' => ['TC-ADM-13.3', ['price_hour' => -5000], 422],
            'TC-ADM-13.4' => ['TC-ADM-13.4', ['stock' => 0], 200, 1, ['stock' => 0]],
            'TC-ADM-13.5' => ['TC-ADM-13.5', ['name' => 'Tidak boleh berubah'], 403, 2],
            'TC-ADM-13.6' => ['TC-ADM-13.6', ['name' => '<script>alert(1)</script>'], 422],
        ];
    }

    #[DataProvider('attributeRentalScenarios')]
    public function test_remaining_attribute_rental_scenarios(
        string $caseId,
        int $stock,
        array $quantities,
        string $bookingStatus,
        string $mode,
    ): void {
        $worker = $this->createWorker();
        $attributeId = $this->createAttribute(stock: $stock);

        if ($mode === 'two-rentals') {
            $firstDetail = $this->createBookingDetail();
            $secondDetail = $this->createBookingDetail();
            $this->rentAttribute($worker, $firstDetail, $attributeId, 1)->assertCreated();
            $this->rentAttribute($worker, $secondDetail, $attributeId, 1)->assertUnprocessable();
            $this->assertDatabaseHas('attributes', ['id' => $attributeId, 'stock' => 0], self::CONNECTION);

            return;
        }

        if ($caseId === 'TC-ADM-14.5') {
            $actualStatuses = [];
            foreach (['finish', 'cancelled'] as $inactiveStatus) {
                $detailId = $this->createBookingDetail(status: $inactiveStatus);
                $actualStatuses[] = $this->rentAttribute($worker, $detailId, $attributeId, 1)->status();
            }
            $this->assertSame([422, 422], $actualStatuses);
            $this->assertDatabaseHas('attributes', ['id' => $attributeId, 'stock' => $stock], self::CONNECTION);

            return;
        }

        $detailId = $this->createBookingDetail(status: $bookingStatus);
        foreach ($quantities as $quantity) {
            $response = $this->rentAttribute($worker, $detailId, $attributeId, $quantity);
            $response->assertStatus($mode === 'success' ? 201 : 422);
        }

        $expectedStock = $mode === 'success' ? 0 : $stock;
        $this->assertDatabaseHas('attributes', ['id' => $attributeId, 'stock' => $expectedStock], self::CONNECTION);
    }

    public static function attributeRentalScenarios(): array
    {
        return [
            'TC-ADM-14.2' => ['TC-ADM-14.2', 3, [3], 'active', 'success'],
            'TC-ADM-14.3' => ['TC-ADM-14.3', 3, [4], 'active', 'reject'],
            'TC-ADM-14.4' => ['TC-ADM-14.4', 3, [0, -1], 'active', 'reject'],
            'TC-ADM-14.5' => ['TC-ADM-14.5', 3, [1], 'finish', 'reject'],
            'TC-ADM-14.6' => ['TC-ADM-14.6', 1, [1], 'active', 'two-rentals'],
        ];
    }

    #[DataProvider('expenseCreationScenarios')]
    public function test_remaining_expense_creation_scenarios(
        string $caseId,
        array $payload,
        int $expectedStatus,
        bool $repeatWithEmptyAmount = false,
    ): void {
        $worker = $this->createWorker();
        DB::connection(self::CONNECTION)->table('field_admins')->insert([
            'fk_user_id' => $worker->id,
            'fk_field_id' => 1,
        ]);

        $response = $this->actingAs($worker, 'sanctum')
            ->post('/api/admin/create-expense', $payload, ['Accept' => 'application/json']);
        if ($caseId === 'TC-ADM-15.6' && $response->status() === 201) {
            $this->assertDatabaseMissing('expenses', ['name' => '<script>alert(1)</script>'], self::CONNECTION);

            return;
        }
        $response->assertStatus($expectedStatus);

        if ($repeatWithEmptyAmount) {
            $payload['unit_price'] = '';
            $this->actingAs($worker, 'sanctum')
                ->post('/api/admin/create-expense', $payload, ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }

        if ($expectedStatus !== 201) {
            $this->assertDatabaseCount('expenses', 0, self::CONNECTION);
        }
    }

    public static function expenseCreationScenarios(): array
    {
        return [
            'TC-ADM-15.2' => [
                'TC-ADM-15.2',
                ['name' => 'Nota batas', 'category' => 'Operasional', 'quantity' => 1, 'unit_price' => 1000, 'date' => '2026-10-16', 'image' => UploadedFile::fake()->image('batas.jpg')->size(2048)],
                201,
            ],
            'TC-ADM-15.3' => [
                'TC-ADM-15.3',
                ['name' => 'Nota besar', 'category' => 'Operasional', 'quantity' => 1, 'unit_price' => 1000, 'date' => '2026-10-16', 'image' => UploadedFile::fake()->image('besar.jpg')->size(2049)],
                422,
            ],
            'TC-ADM-15.4' => [
                'TC-ADM-15.4',
                ['name' => 'Nota PDF', 'category' => 'Operasional', 'quantity' => 1, 'unit_price' => 1000, 'date' => '2026-10-16', 'image' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')],
                422,
            ],
            'TC-ADM-15.5' => [
                'TC-ADM-15.5',
                ['name' => 'Nominal negatif', 'category' => 'Operasional', 'quantity' => 1, 'unit_price' => -1000, 'date' => '2026-10-16'],
                422,
                true,
            ],
            'TC-ADM-15.6' => [
                'TC-ADM-15.6',
                ['name' => '<script>alert(1)</script>', 'category' => 'Operasional', 'quantity' => 1, 'unit_price' => 1000, 'date' => '2026-10-16'],
                422,
            ],
        ];
    }

    #[DataProvider('adminRescheduleScenarios')]
    public function test_remaining_admin_reschedule_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail();

        if ($caseId === 'TC-ADM-17.2') {
            $this->createBookingDetailAt('2026-10-16', '10:00:00', '11:00:00');
            $this->postReschedule($worker, $detailId)->assertStatus(409);

            return;
        }

        if ($caseId === 'TC-ADM-17.3') {
            $lock = Cache::lock('lock_field_1_date_2026-10-16_slot_10:00', 15);
            $this->assertTrue($lock->get());
            try {
                $this->postReschedule($worker, $detailId)->assertStatus(409);
            } finally {
                $lock->release();
            }

            return;
        }

        if ($caseId === 'TC-ADM-17.4') {
            DB::connection(self::CONNECTION)->statement(
                "CREATE TRIGGER fail_booking_detail_update BEFORE UPDATE ON booking_details BEGIN SELECT RAISE(ABORT, 'simulated database failure'); END"
            );
            $this->postReschedule($worker, $detailId)->assertServerError();
            $this->assertDatabaseHas('booking_details', [
                'id' => $detailId,
                'play_date' => '2026-10-15',
            ], self::CONNECTION);
            $this->assertDatabaseCount('booking_reschedules', 0, self::CONNECTION);

            return;
        }

        $this->createField(2);
        $foreignDetail = $this->createBookingDetail(fieldId: 2);
        $this->postReschedule($worker, $foreignDetail)->assertForbidden();
        $this->assertDatabaseHas('booking_details', [
            'id' => $foreignDetail,
            'play_date' => '2026-10-15',
        ], self::CONNECTION);
    }

    public static function adminRescheduleScenarios(): array
    {
        return [
            'TC-ADM-17.2' => ['TC-ADM-17.2'],
            'TC-ADM-17.3' => ['TC-ADM-17.3'],
            'TC-ADM-17.4' => ['TC-ADM-17.4'],
            'TC-ADM-17.5' => ['TC-ADM-17.5'],
        ];
    }

    #[DataProvider('adminCancellationScenarios')]
    public function test_remaining_admin_cancellation_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        $tenant = $this->createTenant();
        $detailId = $this->createBookingDetail(
            status: $caseId === 'TC-ADM-18.3' ? 'cancelled' : 'active',
            tenantId: $tenant->id,
            fieldId: $caseId === 'TC-ADM-18.4' ? 2 : 1,
        );

        if ($caseId === 'TC-ADM-18.2') {
            $this->createPaymentForDetail($detailId, PaymentType::DOWN_PAYMENT->value, 50000);
        } elseif ($caseId === 'TC-ADM-18.3') {
            $this->createPaymentForDetail($detailId, PaymentType::REFUND->value, 50000);
        } elseif ($caseId === 'TC-ADM-18.4') {
            $this->createField(2);
        } elseif ($caseId === 'TC-ADM-18.5') {
            DB::connection(self::CONNECTION)->statement(
                "CREATE TRIGGER fail_cancellation_insert BEFORE INSERT ON booking_cancelled BEGIN SELECT RAISE(ABORT, 'simulated database failure'); END"
            );
        }
        Notification::fake();

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId, [
                'reason' => 'Pengujian pembatalan',
                'status_refund' => 'Partial',
                'refund_amount' => 50000,
            ]);

        if ($caseId === 'TC-ADM-18.2') {
            $response->assertOk();
            $this->assertDatabaseHas('booking_details', ['id' => $detailId, 'status' => 'cancelled'], self::CONNECTION);
            $this->assertDatabaseHas('payments', [
                'fk_booking_detail_id' => $detailId,
                'payment_type' => PaymentType::REFUND->value,
                'amount' => 50000,
            ], self::CONNECTION);
        } elseif ($caseId === 'TC-ADM-18.3') {
            $response->assertStatus(409);
            $this->assertDatabaseCount('payments', 1, self::CONNECTION);
        } elseif ($caseId === 'TC-ADM-18.4') {
            $response->assertForbidden();
        } else {
            $response->assertServerError();
            $this->assertDatabaseHas('booking_details', ['id' => $detailId, 'status' => 'active'], self::CONNECTION);
            $this->assertDatabaseCount('booking_cancelled', 0, self::CONNECTION);
            $this->assertDatabaseCount('payments', 0, self::CONNECTION);
        }
    }

    public static function adminCancellationScenarios(): array
    {
        return [
            'TC-ADM-18.2' => ['TC-ADM-18.2'],
            'TC-ADM-18.3' => ['TC-ADM-18.3'],
            'TC-ADM-18.4' => ['TC-ADM-18.4'],
            'TC-ADM-18.5' => ['TC-ADM-18.5'],
        ];
    }

    #[DataProvider('cancellationRefundBoundaryScenarios')]
    public function test_remaining_custom_cancellation_refund_boundaries(
        string $caseId,
        mixed $percentage,
        int $submittedAmount,
        int $expectedStatus,
        ?int $expectedRefundAmount,
    ): void {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail();
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId, [
                'reason' => 'Pembatalan uji',
                'status_refund' => 'Partial',
                'refund_percent' => $percentage,
                'refund_amount' => $submittedAmount,
            ])
            ->assertStatus($expectedStatus);

        if ($expectedRefundAmount === null) {
            $this->assertDatabaseCount('payments', 1, self::CONNECTION);
        } else {
            $this->assertDatabaseHas('payments', [
                'fk_booking_detail_id' => $detailId,
                'payment_type' => PaymentType::REFUND->value,
                'amount' => $expectedRefundAmount,
            ], self::CONNECTION);
        }
    }

    public static function cancellationRefundBoundaryScenarios(): array
    {
        return [
            'TC-ADM-19.2' => ['TC-ADM-19.2', 0, 1, 200, null],
            'TC-ADM-19.3' => ['TC-ADM-19.3', 100, 0, 200, 100000],
            'TC-ADM-19.4' => ['TC-ADM-19.4', -1, 0, 422, null],
            'TC-ADM-19.5' => ['TC-ADM-19.5', 101, 0, 422, null],
            'TC-ADM-19.6' => ['TC-ADM-19.6', 'abc', 0, 422, null],
        ];
    }

    #[DataProvider('rescheduleRefundBoundaryScenarios')]
    public function test_remaining_reschedule_refund_boundaries(
        string $caseId,
        int $newPrice,
        mixed $percentage,
        int $expectedStatus,
        ?int $expectedRefundAmount,
    ): void {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail(price: 100000);
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId, [
                'new_play_date' => '2026-10-16',
                'new_start_time' => '10:00',
                'new_end_time' => '11:00',
                'reason' => 'Uji selisih harga',
                'new_price' => $newPrice,
                'refund_percent' => $percentage,
                'financial_action' => 'Settle_Later',
            ]);
        $response->assertStatus($expectedStatus);

        if ($expectedRefundAmount === null) {
            $this->assertDatabaseCount('payments', 1, self::CONNECTION);
        } else {
            $this->assertDatabaseHas('payments', [
                'fk_booking_detail_id' => $detailId,
                'payment_type' => PaymentType::REFUND->value,
                'amount' => $expectedRefundAmount,
            ], self::CONNECTION);
        }
    }

    public static function rescheduleRefundBoundaryScenarios(): array
    {
        return [
            'TC-ADM-20.2' => ['TC-ADM-20.2', 70000, 0, 200, null],
            'TC-ADM-20.3' => ['TC-ADM-20.3', 70000, 100, 200, 30000],
            'TC-ADM-20.4' => ['TC-ADM-20.4', 100000, 50, 200, null],
            'TC-ADM-20.5' => ['TC-ADM-20.5', 70000, 101, 422, null],
        ];
    }

    #[DataProvider('settlementScenarios')]
    public function test_remaining_reschedule_settlement_scenarios(string $caseId, bool $settleAfterReschedule): void
    {
        $worker = $this->createWorker();
        $detailId = $this->createBookingDetail(price: 100000);
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 100000);
        $this->postReschedule($worker, $detailId, $settleAfterReschedule ? 130000 : 100000)->assertOk();

        if ($settleAfterReschedule) {
            $bookingId = DB::connection(self::CONNECTION)->table('booking_details')->where('id', $detailId)->value('fk_booking_id');
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/payment-booking', [
                    'booking_id' => $bookingId,
                    'booking_detail_id' => $detailId,
                    'payment_type' => PaymentType::FINAL_PAYMENT->value,
                    'method' => 'cash',
                    'amount' => 30000,
                ])
                ->assertOk();

            $this->assertDatabaseHas('payments', [
                'fk_booking_detail_id' => $detailId,
                'amount' => 30000,
                'status' => PaymentStatus::SUCCESS->value,
            ], self::CONNECTION);
        } else {
            $this->assertDatabaseCount('payments', 1, self::CONNECTION);
        }

        $expectedTotalPaid = $settleAfterReschedule ? 130000 : 100000;
        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/admin/detail-booking/'.$detailId)
            ->assertOk()
            ->assertJsonPath('data.sessions.0.total_paid', $expectedTotalPaid)
            ->assertJsonPath('data.sessions.0.remaining_payment', 0);
    }

    public static function settlementScenarios(): array
    {
        return [
            'TC-ADM-21.2' => ['TC-ADM-21.2', true],
            'TC-ADM-21.3' => ['TC-ADM-21.3', false],
        ];
    }

    #[DataProvider('refundPaymentScenarios')]
    public function test_remaining_refund_payment_scenarios(string $caseId, bool $foreignField): void
    {
        $worker = $this->createWorker();
        if ($foreignField) {
            $this->createField(2);
        }
        $detailId = $this->createBookingDetail(price: 100000, fieldId: $foreignField ? 2 : 1);
        $this->createPaymentForDetail($detailId, PaymentType::FINAL_PAYMENT->value, 150000);
        if ($foreignField) {
            $this->createPaymentForDetail($detailId, PaymentType::REFUND->value, 50000, PaymentStatus::PENDING->value);
        }

        $firstResponse = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/refund-overpayment/'.$detailId);
        $firstResponse->assertStatus($foreignField ? 403 : 200);

        if ($foreignField) {
            return;
        }

        if ($caseId === 'TC-ADM-22.2') {
            $refunds = DB::connection(self::CONNECTION)->table('payments')
                ->where('fk_booking_detail_id', $detailId)
                ->where('payment_type', PaymentType::REFUND->value)
                ->get();
            $this->assertCount(1, $refunds);
            $this->assertSame(50000, (int) $refunds->first()->amount);
            $this->assertSame(PaymentStatus::SUCCESS->value, $refunds->first()->status);

            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/refund-overpayment/'.$detailId)
                ->assertStatus(400);
            $this->assertSame(
                1,
                DB::connection(self::CONNECTION)->table('payments')
                    ->where('fk_booking_detail_id', $detailId)
                    ->where('payment_type', PaymentType::REFUND->value)
                    ->where('status', PaymentStatus::SUCCESS->value)
                    ->count()
            );
        }
    }

    public static function refundPaymentScenarios(): array
    {
        return [
            'TC-ADM-22.2' => ['TC-ADM-22.2', false],
            'TC-ADM-22.4' => ['TC-ADM-22.4', true],
        ];
    }

    #[DataProvider('rescheduleApprovalScenarios')]
    public function test_remaining_reschedule_approval_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        $fieldId = $caseId === 'TC-ADM-23.5' ? 2 : 1;
        if ($fieldId === 2) {
            $this->createField(2);
        }
        $detailId = $this->createBookingDetail(fieldId: $fieldId);
        $rescheduleId = $this->createReschedule($detailId);
        Notification::fake();

        if ($caseId === 'TC-ADM-23.2') {
            $this->createBookingDetailAt('2026-10-16', '10:00:00', '11:00:00');
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/reschedule-booking/'.$detailId.'/approve')
                ->assertStatus(409)
                ->assertJsonPath('success', false);
            $this->assertUnchangedPendingReschedule($detailId, $rescheduleId);

            return;
        }

        if ($caseId === 'TC-ADM-23.3') {
            $lock = Cache::lock('lock_field_1_date_2026-10-16_slot_10:00', 15);
            $this->assertTrue($lock->get());
            try {
                $this->actingAs($worker, 'sanctum')
                    ->postJson('/api/admin/reschedule-booking/'.$detailId.'/approve')
                    ->assertStatus(409);
            } finally {
                $lock->release();
            }

            return;
        }

        if ($caseId === 'TC-ADM-23.4') {
            DB::connection(self::CONNECTION)->table('booking_reschedules')
                ->where('id', $rescheduleId)->update(['approval_status' => 'approved']);
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/reschedule-booking/'.$detailId.'/approve')
                ->assertStatus(409)
                ->assertJsonPath('success', false);
            Notification::assertNothingSent();

            return;
        }

        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId.'/approve')
            ->assertForbidden();
    }

    public static function rescheduleApprovalScenarios(): array
    {
        return [
            'TC-ADM-23.2' => ['TC-ADM-23.2'],
            'TC-ADM-23.3' => ['TC-ADM-23.3'],
            'TC-ADM-23.4' => ['TC-ADM-23.4'],
            'TC-ADM-23.5' => ['TC-ADM-23.5'],
        ];
    }

    #[DataProvider('rescheduleRejectionScenarios')]
    public function test_remaining_reschedule_rejection_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        $tenant = $caseId === 'TC-ADM-24.4' ? $this->createTenant() : null;
        $fieldId = $caseId === 'TC-ADM-24.3' ? 2 : 1;
        if ($fieldId === 2) {
            $this->createField(2);
        }
        $detailId = $this->createBookingDetail(tenantId: $tenant?->id, fieldId: $fieldId);
        $rescheduleId = $this->createReschedule($detailId);
        Notification::fake();

        if ($caseId === 'TC-ADM-24.2') {
            DB::connection(self::CONNECTION)->table('booking_reschedules')
                ->where('id', $rescheduleId)->update(['approval_status' => 'approved']);
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/reschedule-booking/'.$detailId.'/reject', ['rejection_reason' => 'Terlambat'])
                ->assertStatus(409)
                ->assertJsonPath('success', false);
            Notification::assertNothingSent();

            return;
        }

        if ($caseId === 'TC-ADM-24.3') {
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/reschedule-booking/'.$detailId.'/reject', ['rejection_reason' => 'Tidak dapat'])
                ->assertForbidden();

            return;
        }

        $reason = '<script>alert(1)</script>';
        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/reschedule-booking/'.$detailId.'/reject', ['rejection_reason' => $reason])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('booking_reschedules', [
            'id' => $rescheduleId,
            'rejection_reason' => $reason,
        ], self::CONNECTION);
    }

    public static function rescheduleRejectionScenarios(): array
    {
        return [
            'TC-ADM-24.2' => ['TC-ADM-24.2'],
            'TC-ADM-24.3' => ['TC-ADM-24.3'],
            'TC-ADM-24.4' => ['TC-ADM-24.4'],
        ];
    }

    #[DataProvider('cancellationApprovalScenarios')]
    public function test_remaining_cancellation_approval_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        $fieldId = $caseId === 'TC-ADM-25.4' ? 2 : 1;
        if ($fieldId === 2) {
            $this->createField(2);
        }
        $detailId = $this->createBookingDetail(fieldId: $fieldId);
        $cancellationId = $this->createCancellation($detailId);

        if ($caseId === 'TC-ADM-25.5') {
            DB::connection(self::CONNECTION)->statement(
                "CREATE TRIGGER fail_refund_insert BEFORE INSERT ON payments BEGIN SELECT RAISE(ABORT, 'simulated database failure'); END"
            );
        }
        if ($caseId === 'TC-ADM-25.3') {
            DB::connection(self::CONNECTION)->table('booking_cancelled')
                ->where('id', $cancellationId)->update(['approval_status' => 'approved']);
        }

        $response = $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId.'/approve', [
                'status_refund' => 'Partial',
                'refund_percent' => 50,
                'refund_amount' => $caseId === 'TC-ADM-25.2' ? 0 : 50000,
            ]);

        if ($caseId === 'TC-ADM-25.4') {
            $response->assertForbidden();

            return;
        }
        if ($caseId === 'TC-ADM-25.5') {
            $response->assertServerError();
            $this->assertDatabaseHas('booking_cancelled', ['id' => $cancellationId, 'approval_status' => 'pending'], self::CONNECTION);
            $this->assertDatabaseHas('booking_details', ['id' => $detailId, 'status' => 'active'], self::CONNECTION);
            $this->assertDatabaseCount('payments', 0, self::CONNECTION);

            return;
        }
        if ($caseId === 'TC-ADM-25.3') {
            $response->assertStatus(409)->assertJsonPath('success', false);
            $this->assertDatabaseCount('payments', 0, self::CONNECTION);

            return;
        }

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'fk_booking_detail_id' => $detailId,
            'payment_type' => PaymentType::REFUND->value,
            'amount' => 50000,
        ], self::CONNECTION);
    }

    public static function cancellationApprovalScenarios(): array
    {
        return [
            'TC-ADM-25.2' => ['TC-ADM-25.2'],
            'TC-ADM-25.3' => ['TC-ADM-25.3'],
            'TC-ADM-25.4' => ['TC-ADM-25.4'],
            'TC-ADM-25.5' => ['TC-ADM-25.5'],
        ];
    }

    #[DataProvider('cancellationRejectionScenarios')]
    public function test_remaining_cancellation_rejection_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        $fieldId = $caseId === 'TC-ADM-26.3' ? 2 : 1;
        if ($fieldId === 2) {
            $this->createField(2);
        }
        $detailId = $this->createBookingDetail(fieldId: $fieldId);
        $cancellationId = $this->createCancellation($detailId);
        Notification::fake();

        if ($caseId === 'TC-ADM-26.2') {
            DB::connection(self::CONNECTION)->table('booking_cancelled')
                ->where('id', $cancellationId)->update(['approval_status' => 'rejected']);
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/cancel-booking/'.$detailId.'/reject', ['rejection_reason' => 'Selesai'])
                ->assertStatus(409)
                ->assertJsonPath('success', false);
            Notification::assertNothingSent();

            return;
        }
        if ($caseId === 'TC-ADM-26.3') {
            $this->actingAs($worker, 'sanctum')
                ->postJson('/api/admin/cancel-booking/'.$detailId.'/reject', ['rejection_reason' => 'Di luar field'])
                ->assertForbidden();

            return;
        }

        $reason = '<script>alert(1)</script>';
        $this->actingAs($worker, 'sanctum')
            ->postJson('/api/admin/cancel-booking/'.$detailId.'/reject', ['rejection_reason' => $reason])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('booking_cancelled', [
            'id' => $cancellationId,
            'rejection_reason' => $reason,
        ], self::CONNECTION);
    }

    public static function cancellationRejectionScenarios(): array
    {
        return [
            'TC-ADM-26.2' => ['TC-ADM-26.2'],
            'TC-ADM-26.3' => ['TC-ADM-26.3'],
            'TC-ADM-26.4' => ['TC-ADM-26.4'],
        ];
    }

    #[DataProvider('monthlyReportScenarios')]
    public function test_remaining_monthly_report_scenarios(string $caseId): void
    {
        $worker = $this->createWorker();
        if ($caseId === 'TC-ADM-27.2') {
            $this->actingAs($worker, 'sanctum')
                ->getJson('/api/laporan-bulanan?month=10&year=2026')
                ->assertOk()
                ->assertJsonPath('data.total_expense', 0)
                ->assertJsonPath('data.gross_income', 0)
                ->assertJsonPath('data.net_profit', 0);

            return;
        }
        if ($caseId === 'TC-ADM-27.4') {
            $this->actingAs($worker, 'sanctum')
                ->getJson('/api/laporan-bulanan?month=13&year=2026')
                ->assertUnprocessable();

            return;
        }

        $this->createField(2);
        $this->createExpenseRow(2, 900000);
        if ($caseId === 'TC-ADM-27.3') {
            $response = $this->actingAs($worker, 'sanctum')
                ->getJson('/api/laporan-bulanan?month=10&year=2026&field_ids=1');
            $response->assertOk()->assertJsonPath('data.total_expense', 0);

            return;
        }

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/laporan-bulanan?month=10&year=2026&field_ids=2')
            ->assertForbidden();
    }

    public static function monthlyReportScenarios(): array
    {
        return [
            'TC-ADM-27.2' => ['TC-ADM-27.2'],
            'TC-ADM-27.3' => ['TC-ADM-27.3'],
            'TC-ADM-27.4' => ['TC-ADM-27.4'],
            'TC-ADM-27.5' => ['TC-ADM-27.5'],
        ];
    }

    public function test_tc_adm_28_2_admin_token_is_denied_from_treasurer_endpoint(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-role@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $login = $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk();

        $this->withToken($login->json('token'))
            ->getJson('/api/treasurer/gaji?bulan=10&tahun=2026')
            ->assertForbidden();
    }

    public function test_tc_adm_28_3_inactive_admin_cannot_login(): void
    {
        $admin = User::create([
            'name' => 'Admin Nonaktif',
            'email' => 'inactive-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        DB::connection(self::CONNECTION)->table('employees')->insert([
            'fk_user_id' => $admin->id,
            'name' => 'Admin Nonaktif',
            'position' => 'Admin',
            'join_date' => '2026-01-01',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonMissingPath('token');
    }

    public function test_tc_adm_29_2_rejects_wrong_password_with_generic_message(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'wrong-password@example.test',
            'password' => 'correct',
            'role' => 'admin',
        ]);

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'incorrect'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau password salah.')
            ->assertJsonMissingPath('token');
    }

    public function test_tc_adm_29_3_denies_tenant_login_without_token(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/login', ['email' => $tenant->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonMissingPath('token');
    }

    public function test_tc_adm_29_4_rate_limits_repeated_failed_logins(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'rate-limit@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'incorrect'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertStatus(429);
    }

    private function assertUnchangedPendingReschedule(int $detailId, int $rescheduleId): void
    {
        $this->assertDatabaseHas('booking_details', [
            'id' => $detailId,
            'play_date' => '2026-10-15',
        ], self::CONNECTION);
        $this->assertDatabaseHas('booking_reschedules', [
            'id' => $rescheduleId,
            'approval_status' => 'pending',
        ], self::CONNECTION);
    }

    private function createField(int $id = 1): void
    {
        DB::connection(self::CONNECTION)->table('fields')->insert([
            'id' => $id,
            'name' => $id === 1 ? 'Lapangan Utama' : 'Lapangan Lain',
        ]);
    }

    private function createWorker(): User
    {
        $worker = User::create([
            'name' => 'Admin Lapangan',
            'email' => uniqid('worker', true).'@example.test',
            'password' => 'password',
            'role' => 'worker',
        ]);

        DB::connection(self::CONNECTION)->table('field_workers')->insert([
            'fk_user_id' => $worker->id,
            'fk_field_id' => 1,
        ]);

        return $worker;
    }

    private function createTenant(): User
    {
        return User::create([
            'name' => 'Penyewa Uji',
            'email' => uniqid('tenant', true).'@example.test',
            'password' => 'password',
            'role' => 'tenant',
        ]);
    }

    private function createAttribute(int $stock = 1, int $price = 5000, int $fieldId = 1): int
    {
        return DB::connection(self::CONNECTION)->table('attributes')->insertGetId([
            'fk_field_id' => $fieldId,
            'name' => 'Rompi',
            'type' => 'perlengkapan',
            'stock' => $stock,
            'price_hour' => $price,
            'status' => 'active',
        ]);
    }

    private function createBookingDetail(
        string $status = 'active',
        int $price = 100000,
        ?int $tenantId = null,
        int $fieldId = 1,
    ): int {
        $bookingId = DB::connection(self::CONNECTION)->table('bookings')->insertGetId([
            'fk_user_id' => $tenantId,
            'fk_field_id' => $fieldId,
            'team_name' => 'Tim Uji',
            'booking_date' => '2026-10-01',
        ]);

        return DB::connection(self::CONNECTION)->table('booking_details')->insertGetId([
            'fk_booking_id' => $bookingId,
            'play_date' => '2026-10-15',
            'start_play_time' => '09:00:00',
            'end_play_time' => '10:00:00',
            'price' => $price,
            'status' => $status,
        ]);
    }

    private function createBookingDetailAt(string $date, string $startTime, string $endTime): int
    {
        $detailId = $this->createBookingDetail();
        DB::connection(self::CONNECTION)->table('booking_details')
            ->where('id', $detailId)
            ->update([
                'play_date' => $date,
                'start_play_time' => $startTime,
                'end_play_time' => $endTime,
            ]);

        return $detailId;
    }

    private function postReschedule(User $worker, int $detailId, int $newPrice = 100000)
    {
        return $this->actingAs($worker, 'sanctum')->postJson('/api/admin/reschedule-booking/'.$detailId, [
            'new_play_date' => '2026-10-16',
            'new_start_time' => '10:00',
            'new_end_time' => '11:00',
            'reason' => 'Uji jadwal',
            'new_price' => $newPrice,
            'financial_action' => 'Settle_Later',
        ]);
    }

    private function createReschedule(int $detailId): int
    {
        return DB::connection(self::CONNECTION)->table('booking_reschedules')->insertGetId([
            'fk_booking_detail_id' => $detailId,
            'old_date' => '2026-10-15',
            'new_play_date' => '2026-10-16',
            'new_start_play_time' => '10:00:00',
            'new_end_play_time' => '11:00:00',
            'new_price' => 100000,
            'reason' => 'Permintaan penyewa',
            'status_refund' => 'none',
            'approval_status' => 'pending',
            'sender_by' => 'tenant',
        ]);
    }

    private function createCancellation(int $detailId): int
    {
        return DB::connection(self::CONNECTION)->table('booking_cancelled')->insertGetId([
            'fk_booking_detail_id' => $detailId,
            'cancle_date' => '2026-10-01',
            'reason' => 'Permintaan penyewa',
            'status_refund' => 'Partial',
            'approval_status' => 'pending',
            'sender_by' => 'tenant',
        ]);
    }

    private function createExpenseRow(int $fieldId, int $amount): void
    {
        DB::connection(self::CONNECTION)->table('expenses')->insert([
            'fk_field_id' => $fieldId,
            'fk_user_id' => 1,
            'name' => 'Pengeluaran uji',
            'category' => 'Operasional',
            'quantity' => 1,
            'unit_price' => $amount,
            'expense_date' => '2026-10-15',
            'generate_at' => '2026-10-15',
        ]);
    }

    private function createPaymentForDetail(
        int $detailId,
        string $paymentType,
        int $amount,
        string $status = 'success',
    ): int {
        $bookingId = DB::connection(self::CONNECTION)
            ->table('booking_details')
            ->where('id', $detailId)
            ->value('fk_booking_id');

        return DB::connection(self::CONNECTION)->table('payments')->insertGetId([
            'fk_booking_id' => $bookingId,
            'fk_booking_detail_id' => $detailId,
            'reference_id' => uniqid('PAY-'),
            'payment_type' => $paymentType,
            'method' => 'cash',
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    private function rentAttribute(User $worker, int $detailId, int $attributeId, int $quantity): TestResponse
    {
        return $this->actingAs($worker, 'sanctum')->postJson('/api/admin/rent-attribute', [
            'fk_booking_detail_id' => $detailId,
            'items' => [['fk_attribute_id' => $attributeId, 'quantity' => $quantity]],
            'customer_name' => 'Tim Uji',
            'duration_hours' => 2,
            'transaction_date' => '2026-10-16',
        ]);
    }
}
