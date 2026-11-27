<?php

namespace Tests\Feature\Web;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;

class TenantBookingProcessRescheduleTest extends ServiceTestCase
{
    public function test_tenant_can_process_reschedule_successfully()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.process.reschedule'), [
            'detail_booking_id' => $detail->id,
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => ServiceTestData::TIME_10_30_SHORT,
            'new_end_play_time' => ServiceTestData::TIME_11_30_SHORT,
            'reason' => ServiceTestData::RESCHEDULE_REASON,
        ]);

        $response->assertRedirect(route('tenant.booking.history.show', $detail->fk_booking_id));
        $response->assertSessionHas('success');
    }

    public function test_tenant_process_reschedule_fails_when_slot_invalid()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.process.reschedule'), [
            'detail_booking_id' => $detail->id,
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => ServiceTestData::TIME_22_00_SHORT,
            'new_end_play_time' => ServiceTestData::TIME_23_00_SHORT,
            'reason' => ServiceTestData::RESCHEDULE_REASON,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
