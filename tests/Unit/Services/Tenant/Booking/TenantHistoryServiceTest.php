<?php

namespace Tests\Unit\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingReschedule;
use App\Models\Field;
use App\Models\Payment;
use App\Models\User;
use App\Services\Tenant\Booking\TenantHistoryService;
use Illuminate\Pagination\LengthAwarePaginator;
use ReflectionMethod;
use Tests\TestCase;

class TenantHistoryServiceTest extends TestCase
{
    private const TEAM_ELANG = 'Elang FC';
    private const TIME_10 = '10:00:00';
    private const TIME_11 = '11:00:00';

    protected TenantHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantHistoryService();
    }

    public function test_get_available_payment_statuses(): void
    {
        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create();
        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Status Test',
            'booking_date' => now()->toDateString(),
        ]);

        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'ST-1',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'ST-2',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::PENDING->value,
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'ST-3',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::FAILED->value,
        ]);

        $statuses = $this->service->getAvailablePaymentStatuses();

        $this->assertIsArray($statuses);
        $this->assertContains(PaymentStatus::SUCCESS->value, $statuses);
        $this->assertContains(PaymentStatus::PENDING->value, $statuses);
        $this->assertContains(PaymentStatus::FAILED->value, $statuses);
    }

    public function test_get_paginated_history(): void
    {
        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create();

        $booking1 = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Garuda FC',
            'booking_date' => '2026-11-01',
        ]);
        Payment::create([
            'fk_booking_id' => $booking1->id,
            'reference_id' => 'INV-001',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 100000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $booking2 = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => self::TEAM_ELANG,
            'booking_date' => '2026-11-05',
        ]);
        Payment::create([
            'fk_booking_id' => $booking2->id,
            'reference_id' => 'INV-002',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::PENDING->value,
        ]);

        // 1. Search by team name
        $searchResult = $this->service->getPaginatedHistory($tenant->id, ['search' => 'Garuda']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $searchResult);
        $this->assertSame(1, $searchResult->total());
        $this->assertSame('Garuda FC', $searchResult->first()->team_name);

        // 2. Filter by date
        $dateResult = $this->service->getPaginatedHistory($tenant->id, ['date' => '2026-11-05']);
        $this->assertSame(1, $dateResult->total());
        $this->assertSame(self::TEAM_ELANG, $dateResult->first()->team_name);

        // 3. Filter by status
        $statusResult = $this->service->getPaginatedHistory($tenant->id, ['status' => PaymentStatus::PENDING->value]);
        $this->assertSame(1, $statusResult->total());
        $this->assertSame(self::TEAM_ELANG, $statusResult->first()->team_name);
    }

    public function test_get_booking_detail(): void
    {
        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create();

        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Merpati FC',
            'booking_date' => now()->toDateString(),
        ]);

        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => now()->addDays(5)->toDateString(),
            'start_play_time' => self::TIME_10,
            'end_play_time' => self::TIME_11,
            'price' => 150000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'INV-DETAIL-1',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 150000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $result = $this->service->getBookingDetail($tenant->id, $booking->id);

        $this->assertInstanceOf(Booking::class, $result);
        $this->assertSame($booking->id, $result->id);
        $this->assertNotEmpty($result->details);
        $hydratedDetail = $result->details->first();
        $this->assertNotNull($hydratedDetail->detailStatus);
        $this->assertTrue($hydratedDetail->canCancel);
        $this->assertTrue($hydratedDetail->canReschedule);
    }

    public function test_calculate_global_paid_per_session(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'calculateGlobalPaidPerSession');
        $method->setAccessible(true);

        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create();
        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Session Test',
            'booking_date' => now()->toDateString(),
        ]);

        // Create 2 details
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => now()->toDateString(),
            'start_play_time' => self::TIME_10,
            'end_play_time' => self::TIME_11,
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => now()->toDateString(),
            'start_play_time' => self::TIME_11,
            'end_play_time' => '12:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        // Global payment (fk_booking_detail_id = null) of 100,000 DP
        Payment::create([
            'fk_booking_id' => $booking->id,
            'fk_booking_detail_id' => null,
            'reference_id' => 'GLOBAL-DP-1',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 100000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $booking->load(['details', 'payments']);

        $paidPerSession = $method->invoke($this->service, $booking);

        // 100,000 / 2 sessions = 50,000
        $this->assertSame(50000, $paidPerSession);
    }

    public function test_hydrate_detail_item(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'hydrateDetailItem');
        $method->setAccessible(true);

        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create([
            'min_cancel_days' => 3,
            'min_reschedule_days' => 3,
            'max_reschedule_times' => 2,
        ]);
        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Hydrate Test',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => now()->addDays(5)->toDateString(),
            'start_play_time' => self::TIME_10,
            'end_play_time' => self::TIME_11,
            'price' => 120000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $detail->setRelation('reschedules', collect());
        $detail->setRelation('cancellation', null);
        $detail->setRelation('payment', collect());

        $hydrated = $method->invoke($this->service, $detail, 60000, 3, 3, 2);

        $this->assertSame(120000, $hydrated->initialPrice);
        $this->assertSame(60000, $hydrated->initialPaid);
        $this->assertSame('active', $hydrated->detailStatus);
        $this->assertTrue($hydrated->canCancel);
        $this->assertTrue($hydrated->canReschedule);
    }

    public function test_resolve_detail_payments(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'resolveDetailPayments');
        $method->setAccessible(true);

        $detail = new BookingDetail();

        // 1. When payment is null
        $detail->setRelation('payment', null);
        $res1 = $method->invoke($this->service, $detail);
        $this->assertTrue($res1->isEmpty());

        // 2. When payment is a single model
        $payment = new Payment(['amount' => 50000]);
        $detail->setRelation('payment', $payment);
        $res2 = $method->invoke($this->service, $detail);
        $this->assertSame(1, $res2->count());
        $this->assertSame(50000, $res2->first()->amount);

        // 3. When payment is already a Collection
        $coll = collect([new Payment(['amount' => 30000]), new Payment(['amount' => 20000])]);
        $detail->setRelation('payment', $coll);
        $res3 = $method->invoke($this->service, $detail);
        $this->assertSame(2, $res3->count());
    }

    public function test_hydrate_detail_financials(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'hydrateDetailFinancials');
        $method->setAccessible(true);

        $detail = new BookingDetail();
        $payments = collect([
            new Payment(['payment_type' => PaymentType::DOWN_PAYMENT->value, 'status' => PaymentStatus::SUCCESS->value, 'amount' => 40000]),
            new Payment(['payment_type' => PaymentType::RESCHEDULE_FEE->value, 'status' => PaymentStatus::SUCCESS->value, 'amount' => 15000]),
            new Payment(['payment_type' => PaymentType::REFUND->value, 'status' => PaymentStatus::SUCCESS->value, 'amount' => 20000]),
        ]);

        $globalPaidPerSession = 50000;
        $method->invoke($this->service, $detail, $payments, $globalPaidPerSession);

        // initialPaid = global (50,000) + specific (40,000) = 90,000
        $this->assertSame(90000, $detail->initialPaid);
        $this->assertSame(150000 - 135000, $detail->rescheduleFee); // 15,000
        $this->assertSame(105000, $detail->totalDanaMasukSesi); // 90,000 + 15,000
        $this->assertSame(20000, $detail->refundAmount);
        $this->assertSame(85000, $detail->danaHangus); // 105,000 - 20,000
    }

    public function test_hydrate_reschedules_history(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'hydrateReschedulesHistory');
        $method->setAccessible(true);

        $detail = new BookingDetail(['price' => 150000]);

        // 1. Without reschedules
        $detail->setRelation('reschedules', collect());
        $oldPrice = $method->invoke($this->service, $detail, collect());
        $this->assertSame(150000, $oldPrice);

        // 2. With reschedule having deposit required
        $reschedule = new BookingReschedule([
            'status_refund' => 'deposit required',
        ]);
        $detail->setRelation('reschedules', collect([$reschedule]));

        $payments = collect([
            new Payment([
                'payment_type' => PaymentType::RESCHEDULE_FEE->value,
                'reference_id' => 'RSCH-123',
                'amount' => 30000,
            ]),
        ]);

        $calculatedOldPrice = $method->invoke($this->service, $detail, $payments);

        // For deposit required: oldPrice = newPrice (150,000) - diff (30,000) = 120,000
        $this->assertSame(120000, $calculatedOldPrice);
        $this->assertSame('fee', $reschedule->adjustmentType);
        $this->assertSame(30000, $reschedule->diffAmount);
    }

    public function test_hydrate_detail_statuses_and_permissions(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'hydrateDetailStatusesAndPermissions');
        $method->setAccessible(true);

        // 1. Normal active case far in future -> canCancel & canReschedule = true
        $detail = new BookingDetail([
            'play_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ]);
        $detail->setRelation('cancellation', null);
        $detail->setRelation('reschedules', collect());

        $method->invoke($this->service, $detail, null, 3, 3, 1);
        $this->assertTrue($detail->canCancel);
        $this->assertTrue($detail->canReschedule);

        // 2. Play date is tomorrow (less than 3 days) -> canCancel & canReschedule = false
        $nearDetail = new BookingDetail([
            'play_date' => now()->addDays(1)->toDateString(),
            'status' => 'active',
        ]);
        $nearDetail->setRelation('cancellation', null);
        $nearDetail->setRelation('reschedules', collect());

        $method->invoke($this->service, $nearDetail, null, 3, 3, 1);
        $this->assertFalse($nearDetail->canCancel);
        $this->assertFalse($nearDetail->canReschedule);

        // 3. Max reschedule times reached -> canReschedule = false
        $maxedDetail = new BookingDetail([
            'play_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ]);
        $maxedDetail->setRelation('cancellation', null);
        $maxedDetail->setRelation('reschedules', collect([
            new BookingReschedule(['approval_status' => 'approved']),
        ]));

        $method->invoke($this->service, $maxedDetail, null, 3, 3, 1);
        $this->assertTrue($maxedDetail->canCancel);
        $this->assertFalse($maxedDetail->canReschedule);
    }

    public function test_determine_detail_status(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'determineDetailStatus');
        $method->setAccessible(true);

        $detail = new BookingDetail(['status' => 'active']);

        // 1. Cancelled
        $this->assertSame('cancelled', $method->invoke($this->service, $detail, null, true, false, false));

        // 2. Pending cancellation
        $this->assertSame('menunggu pembatalan', $method->invoke($this->service, $detail, null, false, true, false));

        // 3. Pending reschedule
        $this->assertSame('menunggu reschedule', $method->invoke($this->service, $detail, null, false, false, true));

        // 4. Default from detail status
        $this->assertSame('active', $method->invoke($this->service, $detail, null, false, false, false));
    }

    public function test_append_financial_summary(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'appendFinancialSummary');
        $method->setAccessible(true);

        $booking = new Booking();
        $detail1 = new BookingDetail(['price' => 100000, 'status' => 'active']);
        $detail1->setRelation('attributes', collect());
        $detail1->setRelation('cancellation', null);

        $detail2 = new BookingDetail(['price' => 100000, 'status' => 'active']);
        $detail2->setRelation('attributes', collect());
        $detail2->setRelation('cancellation', null);

        $booking->setRelation('details', collect([$detail1, $detail2]));

        $payment = new Payment([
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'status' => PaymentStatus::SUCCESS->value,
            'amount' => 150000,
        ]);
        $booking->setRelation('payments', collect([$payment]));

        $method->invoke($this->service, $booking);

        $this->assertSame(200000, $booking->tagihanAktif);
        $this->assertSame(150000, $booking->uangMasuk);
        $this->assertSame(0, $booking->uangRefund);
        $this->assertSame(50000, $booking->sisaTagihan);
        $this->assertSame('success', $booking->overallStatus);
    }

    public function test_get_badge_class(): void
    {
        $method = new ReflectionMethod(TenantHistoryService::class, 'getBadgeClass');
        $method->setAccessible(true);

        $this->assertSame('bg-emerald-50 text-emerald-700 border-emerald-200', $method->invoke($this->service, 'success'));
        $this->assertSame('bg-amber-50 text-amber-700 border-amber-200', $method->invoke($this->service, 'pending'));
        $this->assertSame('bg-red-50 text-red-700 border-red-200', $method->invoke($this->service, 'cancelled'));
        $this->assertSame('bg-amber-50 text-amber-800 border-amber-300', $method->invoke($this->service, 'menunggu reschedule'));
        $this->assertSame('bg-gray-50 text-gray-700 border-gray-200', $method->invoke($this->service, 'unknown_status'));
    }
}
