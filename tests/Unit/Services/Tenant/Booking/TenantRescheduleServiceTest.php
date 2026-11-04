<?php

namespace Tests\Unit\Services\Tenant\Booking;

use App\Services\Tenant\Booking\TenantRescheduleService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Tests\Unit\Services\ServiceTestData;
use Tests\Unit\Services\ServiceTestCase;

class TenantRescheduleServiceTest extends ServiceTestCase
{
    public function test_get_form_preparation_data(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());
        $result = $service->getFormPreparationData($detail, [
            'date' => ServiceTestData::DATE_26_OCT_2026,
            'month' => ServiceTestData::REPORT_MONTH,
            'year' => ServiceTestData::REPORT_YEAR,
        ]);

        $this->assertSame(ServiceTestData::REPORT_MONTH, $result['month']);
        $this->assertSame(ServiceTestData::REPORT_YEAR, $result['year']);
        $this->assertSame(ServiceTestData::DATE_26_OCT_2026, $result['selectedDate']);
        $this->assertArrayHasKey('slots', $result);
        $this->assertArrayHasKey('canSubmit', $result);
    }

    public function test_validate_and_prepare_review(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $newSlot = [
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => '12:00',
            'new_end_play_time' => '13:00',
        ];

        $result = (new TenantRescheduleService($this->createRefundRuleService()))
            ->validateAndPrepareReview($detail, $newSlot);

        $this->assertSame(ServiceTestData::ONE_HUNDRED_THOUSAND, $result['newPrice']);
        $this->assertSame(ServiceTestData::ONE_HUNDRED_THOUSAND, $result['oldPrice']);
        $this->assertSame(ServiceTestData::ZERO, $result['priceDiff']);
        $this->assertTrue($result['isWithinWindow']);
    }

    public function test_execute_reschedule(): void
    {
        Notification::fake();
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        Auth::login($tenant);
        $newSlot = [
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => '12:00',
            'new_end_play_time' => '13:00',
            'reason' => ServiceTestData::CANCEL_REASON,
        ];

        (new TenantRescheduleService($this->createRefundRuleService()))
            ->executeReschedule($detail, $newSlot);

        $this->assertDatabaseHas('booking_reschedules', [
            'fk_booking_detail_id' => $detail->id,
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'approval_status' => 'pending',
            'sender_by' => 'tenant',
        ], self::DATABASE_CONNECTION_APP);
        Notification::assertSentTo($owner, \App\Notifications\GeneralBookingNotification::class);
        Auth::logout();
    }

    public function test_check_reschedule_rules(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $service->checkRescheduleRules($detail);

        $detail->update(['status' => ServiceTestData::BOOKING_WAITING]);
        try {
            $service->checkRescheduleRules($detail->fresh());
            $this->fail('Booking waiting seharusnya tidak dapat diajukan reschedule.');
        } catch (\UnexpectedValueException $exception) {
            $this->assertSame('Pesanan yang belum dibayar tidak dapat diajukan reschedule.', $exception->getMessage());
        }
    }

    public function test_get_policy(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $policy = $this->invokeServiceMethod($service, 'getPolicy', [$detail]);

        $this->assertNotEmpty($policy['terms']);
        $this->assertTrue($policy['isWithinWindow']);
        $this->assertNotNull($policy['deadline']);
        $this->assertSame(ServiceTestData::ONE_HUNDRED_THOUSAND / ServiceTestData::ONE_THOUSAND, $policy['currentPercent']);
    }

    public function test_check_slot_conflict(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());
        $availableSlot = [
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => '12:00',
            'new_end_play_time' => '13:00',
        ];
        $this->invokeServiceMethod($service, 'checkSlotConflict', [$detail, $availableSlot]);

        try {
            $this->invokeServiceMethod($service, 'checkSlotConflict', [$detail, [
                'new_play_date' => $detail->play_date,
                'new_start_play_time' => '10:00',
                'new_end_play_time' => '11:00',
            ]]);
            $this->fail('Slot yang merupakan bagian jadwal lama seharusnya ditolak.');
        } catch (\UnexpectedValueException $exception) {
            $this->assertStringContainsString('bagian dari jadwal', $exception->getMessage());
        }
    }

    public function test_is_field_closed_on_slot(): void
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        $fieldId = $detail->booking->fk_field_id;
        DB::connection(self::DATABASE_CONNECTION_APP)->table('field_closures')->insert([
            'fk_user_id' => $owner->id,
            'fk_field_id' => $fieldId,
            'field_closure_start_time' => ServiceTestData::DATE_26_OCT_2026 . ' 12:00:00',
            'field_closure_end_time' => ServiceTestData::DATE_26_OCT_2026 . ' 13:00:00',
            'reason' => ServiceTestData::CANCEL_REASON,
        ]);
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $closed = $this->invokeServiceMethod($service, 'isFieldClosedOnSlot', [
            $detail,
            ['new_play_date' => ServiceTestData::DATE_26_OCT_2026],
            '12:30:00',
            '13:30:00',
        ]);

        $this->assertTrue($closed);
    }

    public function test_get_new_price(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $price = $this->invokeServiceMethod($service, 'getNewPrice', [$detail, [
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => '12:00',
            'new_end_play_time' => '13:00',
        ]]);

        $this->assertSame(ServiceTestData::ONE_HUNDRED_THOUSAND, $price);
    }

    public function test_determine_status_refund(): void
    {
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $this->assertSame('deposit required', $this->invokeServiceMethod($service, 'determineStatusRefund', [ServiceTestData::ONE]));
        $this->assertSame('refund required', $this->invokeServiceMethod($service, 'determineStatusRefund', [-ServiceTestData::ONE]));
        $this->assertSame('none', $this->invokeServiceMethod($service, 'determineStatusRefund', [ServiceTestData::ZERO]));
    }

    public function test_generate_calendar(): void
    {
        $service = new TenantRescheduleService($this->createRefundRuleService());
        $calendar = $this->invokeServiceMethod($service, 'generateCalendar', [
            ServiceTestData::REPORT_MONTH,
            ServiceTestData::REPORT_YEAR,
        ]);

        $this->assertNotEmpty($calendar);
        $this->assertArrayHasKey('date', $calendar[ServiceTestData::ZERO]);
        $this->assertArrayHasKey('isCurrentMonth', $calendar[ServiceTestData::ZERO]);
        $this->assertArrayHasKey('isToday', $calendar[ServiceTestData::ZERO]);
        $this->assertArrayHasKey('isPast', $calendar[ServiceTestData::ZERO]);
    }

    public function test_get_slots_for_date(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $slots = $this->invokeServiceMethod($service, 'getSlotsForDate', [
            $detail->booking->fk_field_id,
            ServiceTestData::DATE_26_OCT_2026,
            $detail,
        ]);

        $this->assertNotEmpty($slots);
        $this->assertArrayHasKey('start', $slots[ServiceTestData::ZERO]);
        $this->assertArrayHasKey('is_available', $slots[ServiceTestData::ZERO]);
    }

    public function test_get_closures_for_date(): void
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        DB::connection(self::DATABASE_CONNECTION_APP)->table('field_closures')->insert([
            'fk_user_id' => $owner->id,
            'fk_field_id' => $detail->booking->fk_field_id,
            'field_closure_start_time' => ServiceTestData::DATE_26_OCT_2026 . ' 12:00:00',
            'field_closure_end_time' => ServiceTestData::DATE_26_OCT_2026 . ' 13:00:00',
            'reason' => ServiceTestData::CANCEL_REASON,
        ]);
        $service = new TenantRescheduleService($this->createRefundRuleService());

        $closures = $this->invokeServiceMethod($service, 'getClosuresForDate', [
            $detail->booking->fk_field_id,
            ServiceTestData::DATE_26_OCT_2026,
        ]);

        $this->assertCount(ServiceTestData::ONE, $closures);
    }

    public function test_build_hourly_slots(): void
    {
        [$detail] = $this->createRescheduleFixture();
        $service = new TenantRescheduleService($this->createRefundRuleService());
        $rules = \App\Models\FieldPrice::query()
            ->where('fk_field_id', $detail->booking->fk_field_id)
            ->get();

        $slots = $this->invokeServiceMethod($service, 'buildHourlySlots', [
            $rules,
            collect(),
            [],
            ServiceTestData::DATE_26_OCT_2026,
            $detail,
        ]);

        $this->assertNotEmpty($slots);
        $this->assertTrue($slots[ServiceTestData::ZERO]['is_available']);
    }

    public function test_check_slot_closure_conflict(): void
    {
        $service = new TenantRescheduleService($this->createRefundRuleService());
        $closure = (object) [
            'field_closure_start_time' => ServiceTestData::DATE_26_OCT_2026 . ' 12:00:00',
            'field_closure_end_time' => ServiceTestData::DATE_26_OCT_2026 . ' 13:00:00',
        ];

        $conflict = $this->invokeServiceMethod($service, 'checkSlotClosureConflict', [
            [$closure],
            ServiceTestData::DATE_26_OCT_2026,
            '12:30:00',
            '13:30:00',
        ]);

        $this->assertTrue($conflict);
    }

}
