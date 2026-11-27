<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminNotificationsIndexTest extends ServiceTestCase
{
    public function test_admin_can_get_notifications_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_idx', 'Owner');
        $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/admin/notifications");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
