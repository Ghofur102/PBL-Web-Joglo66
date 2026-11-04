<?php

namespace Tests\Unit\Services\Api;

use App\Enums\NotificationType;
use App\Models\BookingCancelled;
use App\Models\BookingReschedule;
use App\Services\Api\NotificationService;
use Illuminate\Support\Str;
use Tests\Unit\Services\ServiceTestData;
use Tests\Unit\Services\ServiceTestCase;
use Illuminate\Pagination\Paginator;

class NotificationServiceTest extends ServiceTestCase
{
    public function test_get_paginated_notifications(): void
    {
        // **setup test data**
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_BUDI, ServiceTestData::CUSTOMER_BUDI);
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $booking = $this->createBooking($tenant, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_PRIMARY);
        $detail = $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_25_OCT_2026,
            ServiceTestData::TIME_10_00,
            ServiceTestData::TIME_11_00,
            ServiceTestData::BOOKING_ACTIVE,
        );

        BookingCancelled::query()->create([
            'fk_booking_detail_id' => $detail->id,
            'cancle_date' => ServiceTestData::DATE_25_OCT_2026,
            'reason' => ServiceTestData::CANCEL_REASON,
            'status_refund' => ServiceTestData::CANCEL_REFUND_NONE,
            'approval_status' => ServiceTestData::APPROVAL_REJECTED,
            'sender_by' => 'tenant',
            'rejection_reason' => ServiceTestData::REJECT_REASON,
        ]);
        BookingReschedule::query()->create([
            'fk_booking_detail_id' => $detail->id,
            'old_date' => ServiceTestData::DATE_25_OCT_2026,
            'status_refund' => ServiceTestData::CANCEL_REFUND_NONE,
            'approval_status' => ServiceTestData::APPROVAL_APPROVED,
            'sender_by' => ServiceTestData::ROLE_TENANT,
        ]);

        $tenant->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NotificationType::CANCEL_REQUEST->value,
            'data' => [
                'type' => NotificationType::CANCEL_REQUEST->value,
                'booking_detail_id' => $detail->id,
                'booking_id' => $booking->id,
                'title' => ServiceTestData::NOTIFICATION_TITLE,
                'message' => ServiceTestData::NOTIFICATION_MESSAGE,
            ],
        ]);
        $this->createNotification(
            $tenant,
            NotificationType::RESCHEDULE_REQUEST->value,
            [
                'type' => NotificationType::RESCHEDULE_REQUEST->value,
                'booking_detail_id' => $detail->id,
                'title' => ServiceTestData::NOTIFICATION_TITLE,
                'message' => ServiceTestData::NOTIFICATION_MESSAGE,
            ],
        );

        $result = app(NotificationService::class)->getPaginatedNotifications($tenant, ServiceTestData::TEN);

        // **positive test assertions**
        $this->assertSame(ServiceTestData::TWO, $result->total());

        // **testing unknown type**
        $unknownTypeNotification = $this->createNotification(
            $tenant,
            NotificationType::CANCEL_APPROVED->value,
            [
                'type' => NotificationType::CANCEL_APPROVED->value,
                'booking_detail_id' => $detail->id,
                'title' => ServiceTestData::NOTIFICATION_TITLE,
                'message' => ServiceTestData::NOTIFICATION_MESSAGE,
            ],
        );

        $unknownTypeResult = app(NotificationService::class)->getPaginatedNotifications($tenant, ServiceTestData::TEN);
        $unknownTypeItem = $unknownTypeResult->getCollection()->firstWhere('id', $unknownTypeNotification->id);

        $this->assertNull($unknownTypeItem->data['decision_status']);
        $this->assertNull($unknownTypeItem->data['rejection_reason']);

        // **testing missing booking_detail_id**
        $missingDetailNotification = $this->createNotification(
            $tenant,
            NotificationType::CANCEL_REQUEST->value,
            [
                'type' => NotificationType::CANCEL_REQUEST->value,
                'booking_detail_id' => null,
                'title' => ServiceTestData::NOTIFICATION_TITLE,
                'message' => ServiceTestData::NOTIFICATION_MESSAGE,
            ],
        );

        $missingDetailResult = app(NotificationService::class)->getPaginatedNotifications($tenant, ServiceTestData::TEN);
        $missingDetailItem = $missingDetailResult->getCollection()->firstWhere('id', $missingDetailNotification->id);

        $this->assertNull($missingDetailItem->data['decision_status']);
        $this->assertNull($missingDetailItem->data['rejection_reason']);

        // **testing pagination**
        $paginationUser = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_DINA, ServiceTestData::CUSTOMER_DINA);
        $this->createNotification($paginationUser, NotificationType::CANCEL_INFO->value);
        $this->createNotification($paginationUser, NotificationType::RESCHEDULE_INFO->value);
        $this->createNotification($paginationUser, NotificationType::CANCEL_APPROVED->value);

        $paginatedResult = app(NotificationService::class)->getPaginatedNotifications($paginationUser, ServiceTestData::TWO);

        $this->assertSame(ServiceTestData::THREE, $paginatedResult->total());
        $this->assertSame(ServiceTestData::TWO, $paginatedResult->perPage()); // mengecek apakah jumlah tiap halaman benar benar 2

        // mengaktifkan halaman kedua
        Paginator::currentPageResolver(fn () => 2);
        $paginatedResult2 = app(NotificationService::class)->getPaginatedNotifications($paginationUser, ServiceTestData::TWO);

        $this->assertCount(ServiceTestData::ONE, $paginatedResult2->items()); // mengecek apakah jumlah item di halaman pertama benar benar 2
        $this->assertSame(ServiceTestData::TWO, $paginatedResult->lastPage()); // mengecek apakah jumlah halaman benar benar 2
    }

    public function test_get_unread_count(): void
    {
        $user = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_RINA, ServiceTestData::CUSTOMER_RINA);
        $this->createNotification($user, NotificationType::CANCEL_INFO->value, null);
        $this->createNotification($user, NotificationType::RESCHEDULE_INFO->value, null, ServiceTestData::NOTIFICATION_READ_TIMESTAMP);

        $this->assertSame(ServiceTestData::ONE, app(NotificationService::class)->getUnreadCount($user));
    }

    public function test_mark_as_read(): void
    {
        $user = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_SITI, ServiceTestData::CUSTOMER_SITI);
        $notification = $this->createNotification($user, NotificationType::RESCHEDULE_INFO->value);

        $result = app(NotificationService::class)->markAsRead($user, $notification->id);

        $this->assertNotNull($result);
        $this->assertNotNull($result->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $user = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_DINA, ServiceTestData::CUSTOMER_DINA);
        $this->createNotification($user, NotificationType::CANCEL_INFO->value);
        $this->createNotification($user, NotificationType::RESCHEDULE_INFO->value);

        app(NotificationService::class)->markAllAsRead($user);

        $this->assertSame(ServiceTestData::ZERO, $user->fresh()->unreadNotifications()->count());
    }

}
