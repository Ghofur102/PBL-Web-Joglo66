<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminCancelApproveTest extends ServiceTestCase
{
    public function test_admin_can_approve_cancel_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_cancel_app', 'Owner');
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_cancel_app', 'Tenant');
        $detail = $this->createPendingDetail($owner, $tenant);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/cancel-booking/{$detail->id}/approve", [
            'status_refund' => 'Full',
            'refund_amount' => 100000,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_admin_approve_cancel_fails_when_not_found()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_cancel_app2', 'Owner');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/cancel-booking/99999/approve");

        $response->assertStatus(500);
    }
}
