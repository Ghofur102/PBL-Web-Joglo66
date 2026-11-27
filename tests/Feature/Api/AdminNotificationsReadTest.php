<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminNotificationsReadTest extends ServiceTestCase
{
    public function test_admin_can_mark_notification_as_read_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_read', 'Owner');
        $notification = $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
