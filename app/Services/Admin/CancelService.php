<?php

namespace App\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Enums\CancelRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\BookingCancelled;
use App\Models\BookingDetail;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancelService
{
    public function execute(BookingDetail $detail, array $data, User $actor): void
    {
        DB::transaction(function () use ($detail, $data, $actor) {
            BookingCancelled::create([
                'fk_booking_detail_id' => $detail->id,
                'cancle_date'          => now()->toDateString(),
                'reason'               => $data['reason'],
                'status_refund'        => $data['status_refund'],
                'approval_status'      => 'approved',
                'sender_by'            => 'admin',
            ]);

            $detail->update([
                'status' => BookingDetailStatus::CANCELLED->value,
            ]);

            if ($data['status_refund'] !== CancelRefundStatus::NONE->value && ($data['refund_amount'] ?? 0) > 0) {
                Payment::create([
                    'fk_booking_id'        => $detail->fk_booking_id,
                    'fk_booking_detail_id' => $detail->id,
                    'reference_id'         => 'CNL-REF-' . strtoupper(Str::random(10)),
                    'payment_type'         => PaymentType::REFUND->value,
                    'method'               => 'cash',
                    'amount'               => $data['refund_amount'],
                    'status'               => PaymentStatus::SUCCESS->value,
                    'paid_at'              => now(),
                ]);
            }

            $tenant = $detail->booking->user;
            if ($tenant) {
                $tenant->notify(new GeneralBookingNotification([
                    'title'       => 'Jadwal Bermain Dibatalkan',
                    'message'     => "Jadwal sewa pada booking #{$detail->fk_booking_id} telah dibatalkan oleh pihak pengelola.",
                    'type'        => 'cancel_by_admin',
                    'booking_id'  => $detail->fk_booking_id,
                    'url'         => route('tenant.booking.history.show', $detail->fk_booking_id),
                    'sender_id'   => $actor->id,
                    'sender_name' => $actor->name,
                    'sender_role' => $actor->role,
                ]));
            }
        });
    }

    public function approve(BookingDetail $detail, User $actor, array $customRefund = []): void
    {
        DB::transaction(function () use ($detail, $actor, $customRefund) {
            $cancellation = BookingCancelled::where('fk_booking_detail_id', $detail->id)
                ->where('approval_status', 'pending')
                ->latest('id')
                ->first();

            if (!$cancellation) {
                throw new DomainException('Tidak ada pengajuan pembatalan yang menunggu persetujuan.');
            }

            $statusRefund = $customRefund['status_refund'] ?? $cancellation->status_refund ?? 'None';
            $refundAmount = (int) ($customRefund['refund_amount'] ?? 0);

            $cancellation->update([
                'approval_status' => 'approved',
                'status_refund'   => $statusRefund,
            ]);

            $detail->update([
                'status' => BookingDetailStatus::CANCELLED->value,
            ]);

            Payment::where('fk_booking_detail_id', $detail->id)
                ->where('status', PaymentStatus::PENDING->value)
                ->update(['status' => PaymentStatus::FAILED->value]);

            if (strtolower($statusRefund) !== 'none' && $refundAmount > 0) {
                Payment::create([
                    'fk_booking_id'        => $detail->fk_booking_id,
                    'fk_booking_detail_id' => $detail->id,
                    'reference_id'         => 'CNL-REF-' . strtoupper(Str::random(10)),
                    'payment_type'         => PaymentType::REFUND->value,
                    'method'               => 'cash',
                    'amount'               => $refundAmount,
                    'status'               => PaymentStatus::SUCCESS->value,
                    'paid_at'              => now(),
                ]);
            }

            $tenant = $detail->booking->user;
            if ($tenant) {
                $tenant->notify(new GeneralBookingNotification([
                    'title'       => 'Pengajuan Pembatalan Disetujui',
                    'message'     => "Permohonan pembatalan booking #{$detail->fk_booking_id} telah disetujui.",
                    'type'        => 'cancel_approved',
                    'booking_id'  => $detail->fk_booking_id,
                    'url'         => route('tenant.booking.history.show', $detail->fk_booking_id),
                    'sender_id'   => $actor->id,
                    'sender_name' => $actor->name,
                    'sender_role' => $actor->role,
                ]));
            }
        });
    }

    public function reject(BookingDetail $detail, string $reason, User $actor): void
    {
        DB::transaction(function () use ($detail, $reason, $actor) {
            $cancellation = BookingCancelled::where('fk_booking_detail_id', $detail->id)
                ->where('approval_status', 'pending')
                ->latest('id')
                ->first();

            if (!$cancellation) {
                throw new DomainException('Tidak ada pengajuan pembatalan yang menunggu persetujuan.');
            }

            $cancellation->update([
                'approval_status'  => 'rejected',
                'rejection_reason' => $reason,
            ]);

            $tenant = $detail->booking->user;
            if ($tenant) {
                $tenant->notify(new GeneralBookingNotification([
                    'title'       => 'Pengajuan Pembatalan Ditolak',
                    'message'     => "Permohonan pembatalan booking #{$detail->fk_booking_id} ditolak. Alasan: {$reason}",
                    'type'        => 'cancel_rejected',
                    'booking_id'  => $detail->fk_booking_id,
                    'url'         => route('tenant.booking.history.show', $detail->fk_booking_id),
                    'sender_id'   => $actor->id,
                    'sender_name' => $actor->name,
                    'sender_role' => $actor->role,
                ]));
            }
        });
    }
}
