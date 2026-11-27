<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminRescheduleApproveTest extends ServiceTestCase
{
    public function test_admin_can_approve_reschedule_successfully()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        $pendingDetail = $this->createPendingDetail($owner, $tenant);
        $this->createPendingReschedule($pendingDetail);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/reschedule-booking/{$pendingDetail->id}/approve");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_admin_approve_reschedule_not_found()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_resched_app_nf', 'Owner');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/reschedule-booking/99999/approve");

        $response->assertStatus(500);
    }
}
