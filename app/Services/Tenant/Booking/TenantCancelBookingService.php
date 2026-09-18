<?php

namespace App\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Enums\CancelRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\BookingCancelled;
use App\Models\BookingDetail;
use App\Models\BookingReschedule;
use App\Models\FieldWorker;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use Carbon\Carbon;
use DomainException;
use UnexpectedValueException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TenantCancelBookingService
{
    private const DB_CONNECTION = 'mysql_joglo66_app';

    public function getCancellationData(BookingDetail $detail): array
    {
        $field = $detail->booking->field;
        $minCancelDays = (int) ($field->min_cancel_days ?? 3);

        $playDate = Carbon::parse($detail->play_date)->startOfDay();
        $daysUntilPlay = Carbon::now()->startOfDay()->diffInDays($playDate, false);
        $netPaid = $this->calculatePaymentTotals($detail);

        $isRefundable = $daysUntilPlay >= $minCancelDays;
        $refundAmount = $isRefundable ? $netPaid : 0;

        return [
            'playDate'      => $playDate,
            'daysUntilPlay' => $daysUntilPlay,
            'minCancelDays' => $minCancelDays,
            'netPaid'       => $netPaid,
            'isRefundable'  => $isRefundable,
            'refundAmount'  => $refundAmount,
        ];
    }

    public function processCancellation(BookingDetail $detail, string $reason): void
    {
        if (strtolower($detail->status) === 'waiting') {
            throw new UnexpectedValueException('Fitur pembatalan tidak tersedia. Silakan selesaikan pembayaran terlebih dahulu.');
        }

        if ($detail->status === BookingDetailStatus::CANCELLED->value) {
            throw new DomainException('Booking ini sudah dibatalkan sebelumnya.');
        }

        $existingPendingCancel = BookingCancelled::where('fk_booking_detail_id', $detail->id)
            ->where('approval_status', 'pending')
            ->exists();

        if ($existingPendingCancel) {
            throw new DomainException('Pengajuan pembatalan untuk booking ini sedang menunggu persetujuan.');
        }

        $existingPendingReschedule = BookingReschedule::where('fk_booking_detail_id', $detail->id)
            ->where('approval_status', 'pending')
            ->exists();

        if ($existingPendingReschedule) {
            throw new DomainException('Tidak dapat mengajukan pembatalan karena sesi ini sedang menunggu persetujuan reschedule.');
        }

        $cancellationData = $this->getCancellationData($detail);
        $tenantUser = Auth::user();
        $field = $detail->booking->field;

        DB::connection(self::DB_CONNECTION)->transaction(function () use ($detail, $reason, $cancellationData, $tenantUser, $field) {
            BookingCancelled::create([
                'fk_booking_detail_id' => $detail->id,
                'cancle_date'          => now()->toDateString(),
                'reason'               => $reason,
                'status_refund'        => $cancellationData['isRefundable']
                    ? CancelRefundStatus::FULL->value
                    : CancelRefundStatus::NONE->value,
                'approval_status'      => 'pending',
                'sender_by'            => 'tenant',
            ]);

            $workerUserIds = FieldWorker::query()
                ->where('fk_field_id', $field->id)
                ->pluck('fk_user_id');

            $workers = User::whereIn('id', $workerUserIds)->get();
            if ($workers->isNotEmpty()) {
                Notification::send($workers, new GeneralBookingNotification([
                    'title'             => 'Pengajuan Pembatalan Booking',
                    'message'           => "Penyewa {$tenantUser->name} mengajukan pembatalan booking #{$detail->fk_booking_id}. Alasan: {$reason}",
                    'type'              => 'cancel_request',
                    'booking_id'        => $detail->fk_booking_id,
                    'booking_detail_id' => $detail->id,
                    'url'               => "/admin/detail-booking/{$detail->fk_booking_id}",
                    'sender_id'         => $tenantUser->id,
                    'sender_name'       => $tenantUser->name,
                    'sender_role'       => 'tenant',
                ]));
            }

            if ($field->fk_user_id) {
                $owner = User::find($field->fk_user_id);
                if ($owner) {
                    $owner->notify(new GeneralBookingNotification([
                        'title'             => 'Info Pengajuan Pembatalan',
                        'message'           => "Penyewa {$tenantUser->name} mengajukan pembatalan booking #{$detail->fk_booking_id} pada {$field->name}.",
                        'type'              => 'cancel_info',
                        'booking_id'        => $detail->fk_booking_id,
                        'booking_detail_id' => $detail->id,
                        'url'               => "/owner/detail-booking/{$detail->fk_booking_id}",
                        'sender_id'         => $tenantUser->id,
                        'sender_name'       => $tenantUser->name,
                        'sender_role'       => 'tenant',
                    ]));
                }
            }
        });
    }

    public function calculatePaymentTotals(BookingDetail $detail): int
    {
        $allPayments = Payment::query()
            ->where('fk_booking_id', $detail->fk_booking_id)
            ->where('status', PaymentStatus::SUCCESS->value)
            ->get();

        $totalSessions = max(1, $detail->booking->details()->count());

        $globalPaid = $allPayments->whereNull('fk_booking_detail_id')
            ->whereIn('payment_type', [
                PaymentType::DOWN_PAYMENT->value,
                PaymentType::FINAL_PAYMENT->value,
            ])
            ->sum('amount') / $totalSessions;

        $specificPaid = $allPayments->where('fk_booking_detail_id', $detail->id)
            ->whereIn('payment_type', [
                PaymentType::DOWN_PAYMENT->value,
                PaymentType::FINAL_PAYMENT->value,
                PaymentType::RESCHEDULE_FEE->value,
            ])
            ->sum('amount');

        $alreadyRefunded = $allPayments->where('fk_booking_detail_id', $detail->id)
            ->where('payment_type', PaymentType::REFUND->value)
            ->sum('amount');

        return max(0, (int) round(($globalPaid + $specificPaid) - $alreadyRefunded));
    }
}
