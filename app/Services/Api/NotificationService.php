<?php

namespace App\Services\Api;

use App\Enums\ApprovalStatus;
use App\Enums\NotificationType;
use App\Models\BookingCancelled;
use App\Models\BookingDetail;
use App\Models\BookingReschedule;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function getPaginatedNotifications(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $notifications = $user->notifications()->paginate($perPage);

        $notifications->getCollection()->transform(function ($notification) {
            $data = $notification->data;
            $type = $data['type'] ?? '';

            $detailId = $data['booking_detail_id'] ?? null;
            if (!$detailId && !empty($data['booking_id'])) {
                $detailId = BookingDetail::where('fk_booking_id', $data['booking_id'])->value('id');
            }

            $data['decision_status'] = null;
            $data['rejection_reason'] = null;

            if ($detailId) {
                if (in_array($type, [NotificationType::CANCEL_REQUEST->value, NotificationType::CANCEL_INFO->value], true)) {
                    $cancellation = BookingCancelled::where('fk_booking_detail_id', $detailId)
                        ->latest('id')
                        ->first();

                    if ($cancellation) {
                        $data['decision_status'] = $cancellation->approval_status;
                        $data['rejection_reason'] = $cancellation->rejection_reason;
                    }
                } elseif (in_array($type, [NotificationType::RESCHEDULE_REQUEST->value, NotificationType::RESCHEDULE_INFO->value], true)) {
                    $reschedule = BookingReschedule::where('fk_booking_detail_id', $detailId)
                        ->latest('id')
                        ->first();

                    if ($reschedule) {
                        $data['decision_status'] = $reschedule->approval_status;
                        $data['rejection_reason'] = $reschedule->rejection_reason;
                    }
                }
            }

            $notification->data = $data;
            return $notification;
        });

        return $notifications;
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $id): ?DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return $notification;
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
