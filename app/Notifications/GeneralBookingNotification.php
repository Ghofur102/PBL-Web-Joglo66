<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GeneralBookingNotification extends Notification
{
    use Queueable;

    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => $this->payload['title'] ?? 'Pemberitahuan',
            'message'    => $this->payload['message'] ?? '',
            'type'       => $this->payload['type'] ?? 'info',
            'booking_id' => $this->payload['booking_id'] ?? null,
            'url'        => $this->payload['url'] ?? null,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
