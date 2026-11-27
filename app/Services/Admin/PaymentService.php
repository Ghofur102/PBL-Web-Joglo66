<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\BookingDetail;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\BookingDetailStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function process(array $payload): array
    {
        $booking = Booking::query()->with('details')->findOrFail($payload['booking_id']);

        $this->verifyBookingIntegrity($booking);

        $financialAdjustment = $this->verifyAndAdjustFinancials($booking, $payload);
        $adjustedAmount = $financialAdjustment['adjusted_amount'];
        $warningMessage = $financialAdjustment['warning'];

        $referenceId = $payload['reference_id'] ?? 'CSH-' . Str::upper(Str::random(12));
        $paymentStatus = PaymentStatus::SUCCESS->value;

        $payment = DB::transaction(function () use ($booking, $payload, $referenceId, $paymentStatus, $adjustedAmount) {
            $createdPayment = Payment::create([
                'fk_booking_id'        => $booking->id,
                'fk_booking_detail_id' => $payload['booking_detail_id'] ?? null,
                'reference_id'         => $referenceId,
                'payment_url'          => null,
                'payment_type'         => $payload['payment_type'],
                'method'               => $payload['method'],
                'amount'               => $adjustedAmount,
                'status'               => $paymentStatus,
                'paid_at'              => now(),
            ]);

            if (in_array($payload['payment_type'], [PaymentType::DOWN_PAYMENT->value, PaymentType::FINAL_PAYMENT->value], true)) {
                $booking->details()
                    ->where('status', BookingDetailStatus::WAITING->value)
                    ->when($payload['booking_detail_id'] ?? null, function ($query, $detailId) {
                        return $query->where('id', $detailId);
                    })
                    ->update([
                        'status' => BookingDetailStatus::ACTIVE->value
                    ]);
            }

            return $createdPayment;
        });

        return [
            'payment' => $payment,
            'warning' => $warningMessage
        ];
    }

    private function verifyBookingIntegrity(Booking $booking): void
    {
        if ($booking->details->isEmpty()) {
            throw new HttpException(400, 'Booking has no associated details.');
        }

        $allCancelled = $booking->details->every(function ($detail) {
            return $detail->status === BookingDetailStatus::CANCELLED->value;
        });

        if ($allCancelled) {
            throw new HttpException(400, 'Cannot process payment for a fully cancelled booking.');
        }
    }

    private function verifyAndAdjustFinancials(Booking $booking, array $payload): array
    {
        $detailId = $payload['booking_detail_id'] ?? null;
        $warning = null;

        if ($detailId) {
            $detail = $booking->details->firstWhere('id', $detailId);
            if (!$detail) {
                throw new HttpException(400, 'Detail sesi booking tidak ditemukan.');
            }

            $totalPrice = $detail->price;

            $totalPaid = Payment::query()->where('fk_booking_id', $booking->id)
                ->where('fk_booking_detail_id', $detailId)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->whereIn('payment_type', [PaymentType::DOWN_PAYMENT->value, PaymentType::FINAL_PAYMENT->value, PaymentType::RESCHEDULE_FEE->value])
                ->sum('amount');

            $totalRefunded = Payment::query()->where('fk_booking_id', $booking->id)
                ->where('fk_booking_detail_id', $detailId)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->where('payment_type', PaymentType::REFUND->value)
                ->sum('amount');

            $globalDp = Payment::query()->where('fk_booking_id', $booking->id)
                ->whereNull('fk_booking_detail_id')
                ->where('payment_type', PaymentType::DOWN_PAYMENT->value)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->sum('amount');

            if ($globalDp > 0 && $totalPaid == 0) {
                $totalPaid = $globalDp / $booking->details->count();
            }
        } else {
            $totalPrice = $booking->details->sum(fn($d) => $d->price);

            $totalPaid = Payment::query()->where('fk_booking_id', $booking->id)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->whereIn('payment_type', [PaymentType::DOWN_PAYMENT->value, PaymentType::FINAL_PAYMENT->value, PaymentType::RESCHEDULE_FEE->value])
                ->sum('amount');

            $totalRefunded = Payment::query()->where('fk_booking_id', $booking->id)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->where('payment_type', PaymentType::REFUND->value)
                ->sum('amount');
        }

        $netPaid = $totalPaid - $totalRefunded;
        $adjustedAmount = (int)$payload['amount'];

        if (in_array($payload['payment_type'], [PaymentType::DOWN_PAYMENT->value, PaymentType::FINAL_PAYMENT->value, PaymentType::RESCHEDULE_FEE->value], true)) {
            if ($netPaid >= $totalPrice) {
                throw new HttpException(400, 'Sesi ini sudah lunas sepenuhnya.');
            }

            $expectedAmount = (int)($totalPrice - $netPaid);

            if ($payload['payment_type'] === PaymentType::DOWN_PAYMENT->value) {
                $expectedAmount = (int)($totalPrice * 0.5);

                $hasDownPayment = Payment::query()->where('fk_booking_id', $booking->id)
                    ->when($detailId, fn($q) => $q->where('fk_booking_detail_id', $detailId))
                    ->where('payment_type', PaymentType::DOWN_PAYMENT->value)
                    ->where('status', PaymentStatus::SUCCESS->value)
                    ->exists();

                if ($hasDownPayment) {
                    throw new HttpException(400, 'Down Payment (DP) sudah dibayarkan sebelumnya.');
                }
            }

            if ((int)$payload['amount'] !== $expectedAmount) {
                $adjustedAmount = $expectedAmount;
                $warning = "Peringatan Keamanan: Request nominal pembayaran (Rp " . number_format($payload['amount'], 0, ',', '.') . ") tidak valid. Sistem otomatis menyesuaikan transaksi ke nilai resmi server sebesar Rp " . number_format($expectedAmount, 0, ',', '.') . ".";
            }
        }

        return [
            'adjusted_amount' => $adjustedAmount,
            'warning'         => $warning
        ];
    }
}
