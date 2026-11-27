<?php

namespace Tests\Feature\Web;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;

class TenantBookingProcessCancelledTest extends ServiceTestCase
{
    public function test_tenant_can_process_cancellation_successfully()
    {
        [$detail, $tenant] = $this->createCancellationFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.process.cancelled'), [
            'detail_booking_id' => $detail->id,
            'reason' => ServiceTestData::CANCEL_REASON,
            'field_id' => $detail->booking->fk_field_id,
        ]);

        $response->assertRedirect(route('tenant.booking.history.show', $detail->fk_booking_id));
        $response->assertSessionHas('success');
    }

    public function test_tenant_process_cancellation_fails_on_missing_detail()
    {
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_cancel_proc2', 'Tenant');

        $response = $this->actingAs($tenant)->post(route('tenant.booking.process.cancelled'), [
            'detail_booking_id' => 99999,
            'reason' => ServiceTestData::CANCEL_REASON,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['detail_booking_id']);
    }
}
