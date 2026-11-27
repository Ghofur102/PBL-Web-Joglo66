<?php

namespace Tests\Feature\Web;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use App\Models\BookingDetail;

class TenantBookingFormRescheduleTest extends ServiceTestCase
{
    public function test_tenant_can_view_reschedule_form_successfully()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();

        $response = $this->actingAs($tenant)->get(route('tenant.booking.form.reschedule', $detail->id));

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.reschedule.index');
        $response->assertViewHas('detail');
    }

    public function test_tenant_cannot_view_reschedule_form_for_other_users_booking()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        $otherTenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'other_tenant_reschedule', 'Other Tenant');

        $response = $this->actingAs($otherTenant)->get(route('tenant.booking.form.reschedule', $detail->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
