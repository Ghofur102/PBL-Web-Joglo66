<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class OwnerNotificationsReadTest extends ServiceTestCase
{
    public function test_owner_can_mark_notification_as_read_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_read2', 'Owner');
        $notification = $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/owner/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
