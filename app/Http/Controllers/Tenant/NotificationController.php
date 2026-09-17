<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;
    const ROUTE_NOTIFICATION_INDEX = 'tenant.notifications.index';

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(): View
    {
        $user = Auth::user();
        $notifications = $this->notificationService->getPaginatedNotifications($user);
        $unreadCount = $this->notificationService->getUnreadCount($user);

        return view(self::ROUTE_NOTIFICATION_INDEX, compact('notifications', 'unreadCount'));
    }

    public function read(string $id): RedirectResponse
    {
        $user = Auth::user();
        $notification = $this->notificationService->markAsRead($user, $id);

        if (!$notification) {
            return redirect()->route(self::ROUTE_NOTIFICATION_INDEX);
        }

        $targetUrl = $notification->data['url'] ?? route(self::ROUTE_NOTIFICATION_INDEX);

        return redirect()->to($targetUrl);
    }

    public function readAll(): RedirectResponse
    {
        $user = Auth::user();
        $this->notificationService->markAllAsRead($user);

        return redirect()->back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    }
}
