<?php

namespace App\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Enums\CancelRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\BookingCancelled;
use App\Models\BookingDetail;
use App\Models\Payment;
use Carbon\Carbon;
use DomainException;
use UnexpectedValueException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantCancelBookingService
{
    private const DB_CONNECTION = 'mysql_joglo66_app';

    public function getCancellationData(BookingDetail $detail): array
    {
        $playDate = Carbon::parse($detail->play_date)->startOfDay();
        $daysUntilPlay = Carbon::now()->startOfDay()->diffInDays($playDate, false);
        $netPaid = $this->calculatePaymentTotals($detail);

        $isRefundable = $daysUntilPlay >= 3;
        $refundAmount = $isRefundable ? $netPaid : 0;

        return [
            'playDate'      => $playDate,
            'daysUntilPlay' => $daysUntilPlay,
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

        $cancellationData = $this->getCancellationData($detail);

        DB::connection(self::DB_CONNECTION)->transaction(function () use ($detail, $reason, $cancellationData) {

            Payment::query()
                ->where('fk_booking_detail_id', $detail->id)
                ->where('status', PaymentStatus::PENDING->value)
                ->update(['status' => PaymentStatus::FAILED->value]);

            BookingCancelled::create([
                'fk_booking_detail_id' => $detail->id,
                'cancle_date'          => now()->toDateString(),
                'reason'               => $reason,
                'status_refund'        => $cancellationData['isRefundable']
                    ? CancelRefundStatus::FULL->value
                    : CancelRefundStatus::NONE->value,
            ]);

            $detail->update(['status' => BookingDetailStatus::CANCELLED->value]);

            if ($cancellationData['isRefundable'] && $cancellationData['refundAmount'] > 0) {
                Payment::create([
                    'fk_booking_id'        => $detail->fk_booking_id,
                    'fk_booking_detail_id' => $detail->id,
                    'reference_id'         => 'CNL-REF-' . Str::upper(Str::random(10)),
                    'payment_type'         => PaymentType::REFUND->value,
                    'method'               => 'cash',
                    'amount'               => $cancellationData['refundAmount'],
                    'status'               => PaymentStatus::SUCCESS->value,
                    'paid_at'              => now(),
                ]);
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
