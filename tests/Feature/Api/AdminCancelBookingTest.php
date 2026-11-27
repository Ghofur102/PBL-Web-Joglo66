<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminCancelBookingTest extends ServiceTestCase
{
    public function test_admin_can_cancel_booking_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_cancel_api', 'Owner');
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_cancel_api', 'Tenant');
        $detail = $this->createDetail($owner, $tenant);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/cancel-booking/{$detail->id}", [
            'reason' => ServiceTestData::CANCEL_REASON,
            'status_refund' => 'None',
            'refund_amount' => ServiceTestData::ZERO,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_admin_cancel_booking_validation_fails_without_reason()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_cancel_api2', 'Owner');
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_cancel_api2', 'Tenant');
        $detail = $this->createDetail($owner, $tenant);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/cancel-booking/{$detail->id}");

        $response->assertStatus(422);
    }
}
