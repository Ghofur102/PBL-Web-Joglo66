<?php

namespace App\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\BookingDetail;
use App\Models\BookingReschedule;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RescheduleService
{
    private const NO_PENDING_RESCHEDULE = 'Tidak ada pengajuan reschedule yang menunggu persetujuan.';

    public function execute(BookingDetail $detail, array $data, User $actor): void
    {
        $fieldId = (int) $detail->booking->fk_field_id;

        $this->withSlotLock($fieldId, $data['new_play_date'], $data['new_start_time'], function () use ($detail, $data, $actor, $fieldId) {
            DB::transaction(fn () => $this->executeTransaction($detail, $data, $actor, $fieldId));
        });
    }

    private function executeTransaction(BookingDetail $detail, array $data, User $actor, int $fieldId): void
    {
        $this->assertNoSlotConflict($detail, $data, $fieldId);

        $oldPrice = (int) $detail->price;
        $newPrice = (int) $data['new_price'];
        $statusRefund = $this->resolveRefundStatus($oldPrice, $newPrice);

        $this->createRescheduleRecord($detail, $data, $newPrice, $statusRefund);
        $this->updateRescheduledDetail($detail, $data);
        $this->createRefundPayment($detail, $data, $oldPrice, $newPrice);
        $this->createFinalPayment($detail, $data);
        $this->notifyRescheduledTenant($detail, $data, $actor);
    }

    private function assertNoSlotConflict(BookingDetail $detail, array $data, int $fieldId): void
    {
        $hasConflict = $this->hasSlotConflict(
            $fieldId,
            $detail->id,
            $data['new_play_date'],
            $data['new_start_time'],
            $data['new_end_time']
        );

        if ($hasConflict) {
            throw new DomainException('Slot jadwal yang diajukan telah diambil oleh pemesanan lain.');
        }
    }

    private function resolveRefundStatus(int $oldPrice, int $newPrice): string
    {
        if ($newPrice > $oldPrice) {
            return 'deposit required';
        }

        return $newPrice < $oldPrice ? 'refund required' : 'none';
    }

    private function createRescheduleRecord(BookingDetail $detail, array $data, int $newPrice, string $statusRefund): void
    {
        BookingReschedule::create([
            'fk_booking_detail_id' => $detail->id,
            'old_date' => $detail->play_date,
            'new_play_date' => $data['new_play_date'],
            'new_start_play_time' => $data['new_start_time'],
            'new_end_play_time' => $data['new_end_time'],
            'new_price' => $newPrice,
            'reason' => $data['reason'],
            'status_refund' => $statusRefund,
            'approval_status' => 'approved',
            'sender_by' => 'admin',
        ]);
    }

    private function updateRescheduledDetail(BookingDetail $detail, array $data): void
    {
        $detail->update([
            'play_date' => $data['new_play_date'],
            'start_play_time' => $data['new_start_time'],
            'end_play_time' => $data['new_end_time'],
            'price' => $data['new_price'],
            'status' => BookingDetailStatus::RESCHEDULE->value,
        ]);
    }

    private function createRefundPayment(BookingDetail $detail, array $data, int $oldPrice, int $newPrice): void
    {
        if ($newPrice >= $oldPrice) {
            return;
        }

        $refundAmount = (int) round(($oldPrice - $newPrice) * ((float) ($data['refund_percent'] ?? 0) / 100));
        if ($refundAmount <= 0) {
            return;
        }

        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'fk_booking_detail_id' => $detail->id,
            'reference_id' => 'RSC-REF-'.strtoupper(Str::random(10)),
            'payment_type' => PaymentType::REFUND->value,
            'method' => 'cash',
            'amount' => $refundAmount,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);
    }

    private function createFinalPayment(BookingDetail $detail, array $data): void
    {
        $isLunas = ($data['financial_action'] ?? '') === 'Lunas';
        $reconciledAmount = (int) ($data['reconciled_amount'] ?? 0);
        if (! $isLunas || $reconciledAmount <= 0) {
            return;
        }

        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'fk_booking_detail_id' => $detail->id,
            'reference_id' => 'RSC-'.strtoupper(Str::random(10)),
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => $reconciledAmount,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);
    }

    private function notifyRescheduledTenant(BookingDetail $detail, array $data, User $actor): void
    {
        $tenant = $detail->booking->user;
        if (! $tenant) {
            return;
        }

        $tenant->notify(new GeneralBookingNotification([
            'title' => 'Jadwal Bermain Telah Diubah',
            'message' => "Jadwal sewa pada booking #{$detail->fk_booking_id} telah disesuaikan oleh pengelola ke tanggal {$data['new_play_date']}.",
            'type' => 'reschedule_by_admin',
            'booking_id' => $detail->fk_booking_id,
            'url' => route('tenant.booking.history.show', $detail->fk_booking_id),
            'sender_id' => $actor->id,
            'sender_name' => $actor->name,
            'sender_role' => $actor->role,
        ]));
    }

    public function approve(BookingDetail $detail, User $actor): void
    {
        $reschedule = BookingReschedule::where('fk_booking_detail_id', $detail->id)
            ->where('approval_status', 'pending')
            ->latest('id')
            ->first();

        if (! $reschedule) {
            throw new DomainException(self::NO_PENDING_RESCHEDULE);
        }

        $fieldId = (int) $detail->booking->fk_field_id;
        $this->withSlotLock($fieldId, $reschedule->new_play_date, $reschedule->new_start_play_time, function () use ($detail, $actor, $fieldId) {
            DB::transaction(function () use ($detail, $actor, $fieldId) {
                $reschedule = BookingReschedule::where('fk_booking_detail_id', $detail->id)
                    ->where('approval_status', 'pending')
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if (! $reschedule) {
                    throw new DomainException(self::NO_PENDING_RESCHEDULE);
                }

                if ($this->hasSlotConflict(
                    $fieldId,
                    $detail->id,
                    $reschedule->new_play_date,
                    $reschedule->new_start_play_time,
                    $reschedule->new_end_play_time
                )) {
                    throw new DomainException('Slot jadwal yang diajukan telah diambil oleh pemesanan lain.');
                }

                $reschedule->update([
                    'approval_status' => 'approved',
                ]);

                $detail->update([
                    'play_date' => $reschedule->new_play_date,
                    'start_play_time' => $reschedule->new_start_play_time,
                    'end_play_time' => $reschedule->new_end_play_time,
                    'price' => $reschedule->new_price,
                    'status' => BookingDetailStatus::RESCHEDULE->value,
                ]);

                $tenant = $detail->booking->user;
                if ($tenant) {
                    $tenant->notify(new GeneralBookingNotification([
                        'title' => 'Pengajuan Reschedule Disetujui',
                        'message' => "Jadwal baru pilihan Anda untuk booking #{$detail->fk_booking_id} telah disetujui oleh admin.",
                        'type' => 'reschedule_approved',
                        'booking_id' => $detail->fk_booking_id,
                        'url' => route('tenant.booking.history.show', $detail->fk_booking_id),
                        'sender_id' => $actor->id,
                        'sender_name' => $actor->name,
                        'sender_role' => $actor->role,
                    ]));
                }
            });
        });
    }

    private function withSlotLock(int $fieldId, string $date, string $startTime, callable $callback): void
    {
        $dateKey = Carbon::parse($date)->toDateString();
        $timeKey = Carbon::parse($startTime)->format('H:i');
        $locks = [
            Cache::lock("lock_field_{$fieldId}_date_{$dateKey}", 15),
            Cache::lock("lock_field_{$fieldId}_date_{$dateKey}_slot_{$timeKey}", 15),
        ];
        $acquired = [];

        try {
            foreach ($locks as $lock) {
                if (! $lock->get()) {
                    throw new DomainException('Slot jadwal sedang diproses oleh permintaan lain.');
                }
                $acquired[] = $lock;
            }

            $callback();
        } finally {
            foreach (array_reverse($acquired) as $lock) {
                $lock->release();
            }
        }
    }

    private function hasSlotConflict(
        int $fieldId,
        int $detailId,
        string $date,
        string $startTime,
        string $endTime,
    ): bool {
        return BookingDetail::whereHas('booking', fn ($query) => $query->where('fk_field_id', $fieldId))
            ->where('id', '!=', $detailId)
            ->whereDate('play_date', Carbon::parse($date)->toDateString())
            ->whereNotIn('status', [
                BookingDetailStatus::CANCELLED->value,
                BookingDetailStatus::CLOSED_FIELD_CANCELLED->value,
                BookingDetailStatus::FINISH->value,
                BookingDetailStatus::FIELD_CLOSURE->value,
            ])
            ->where('start_play_time', '<', $endTime)
            ->where('end_play_time', '>', $startTime)
            ->exists();
    }

    public function reject(BookingDetail $detail, string $reason, User $actor): void
    {
        DB::transaction(function () use ($detail, $reason, $actor) {
            $reschedule = BookingReschedule::where('fk_booking_detail_id', $detail->id)
                ->where('approval_status', 'pending')
                ->latest('id')
                ->first();

            if (! $reschedule) {
                throw new DomainException(self::NO_PENDING_RESCHEDULE);
            }

            $reschedule->update([
                'approval_status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            $tenant = $detail->booking->user;
            if ($tenant) {
                $tenant->notify(new GeneralBookingNotification([
                    'title' => 'Pengajuan Reschedule Ditolak',
                    'message' => "Pengajuan reschedule booking #{$detail->fk_booking_id} ditolak. Alasan: {$reason}",
                    'type' => 'reschedule_rejected',
                    'booking_id' => $detail->fk_booking_id,
                    'url' => route('tenant.booking.history.show', $detail->fk_booking_id),
                    'sender_id' => $actor->id,
                    'sender_name' => $actor->name,
                    'sender_role' => $actor->role,
                ]));
            }
        });
    }
}
