<?php

namespace App\Services\Tenant;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function getPaginatedNotifications(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $notificationId): ?DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $notificationId)->first();

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
