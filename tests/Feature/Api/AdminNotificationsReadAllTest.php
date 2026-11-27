<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminNotificationsReadAllTest extends ServiceTestCase
{
    public function test_admin_can_mark_all_notifications_as_read_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_all', 'Owner');
        $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/notifications/read-all");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
