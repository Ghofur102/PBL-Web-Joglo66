<?php

namespace App\Services\Tenant\Payment;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Enums\PaymentStatus;
use App\Enums\BookingDetailStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;

class TenantDuitkuService
{
    private const CODE_DUITKU_SUCCESS = '00';

    public function processWebhookCallback(array $payload): void
    {
        Log::info('--- DUITKU CALLBACK: Memproses Notifikasi ---', [
            'merchantOrderId' => $payload['merchantOrderId'] ?? null,
            'reference'       => $payload['reference'] ?? null,
            'resultCode'      => $payload['resultCode'] ?? null,
            'amount'          => $payload['amount'] ?? null,
        ]);

        $merchantCode = trim((string) (config('duitku.merchant_code') ?: config('services.duitku.merchant_code') ?: env('DUITKU_MERCHANT_CODE', '')));
        $apiKey       = trim((string) (config('duitku.api_key') ?: config('services.duitku.merchant_key') ?: env('DUITKU_API_KEY', env('DUITKU_MERCHANT_KEY', ''))));

        $this->verifyDuitkuSignature($payload, $merchantCode, $apiKey);

        // Cari data Payment berdasarkan reference_id atau booking_id
        $payment = null;
        if (!empty($payload['reference'])) {
            $payment = Payment::query()->where('reference_id', $payload['reference'])->first();
        }

        if (!$payment && !empty($payload['merchantOrderId'])) {
            $bookingId = explode('-', $payload['merchantOrderId'])[0];
            $payment = Payment::query()->where('fk_booking_id', $bookingId)->latest()->first();
        }

        if (!$payment) {
            Log::error('DUITKU CALLBACK Gagal: Transaksi pembayaran tidak ditemukan di database.', [
                'reference'       => $payload['reference'] ?? null,
                'merchantOrderId' => $payload['merchantOrderId'] ?? null,
            ]);
            throw new UnexpectedValueException('Payment Not Found', 404);
        }

        // Simpan reference Duitku jika sebelumnya belum terisi
        if (empty($payment->reference_id) && !empty($payload['reference'])) {
            $payment->update(['reference_id' => $payload['reference']]);
        }

        Log::info("Data Payment Ditemukan [ID: {$payment->id}]. Status saat ini: {$payment->status}");

        $this->updatePaymentAndBookingStates($payment, $payload);
    }

    /**
     * Memvalidasi keaslian signature yang dikirimkan oleh webhook Duitku.
     * Formula: MD5(merchantCode + amount + merchantOrderId + apiKey)
     */
    private function verifyDuitkuSignature(array $payload, string $merchantCode, string $apiKey): void
    {
        $orderId = $payload['merchantOrderId'] ?? '';
        $amountRaw = $payload['amount'] ?? '';
        $amountInt = (string) (int) round((float) $amountRaw);
        $receivedSignature = (string) ($payload['signature'] ?? '');

        // Hitung signature baik dengan string asli amount maupun integer
        $calcSignatureRaw = md5($merchantCode . $amountRaw . $orderId . $apiKey);
        $calcSignatureInt = md5($merchantCode . $amountInt . $orderId . $apiKey);

        $isValid = hash_equals(strtolower($calcSignatureRaw), strtolower($receivedSignature))
            || hash_equals(strtolower($calcSignatureInt), strtolower($receivedSignature));

        if (!$isValid) {
            Log::error('DUITKU CALLBACK: Bad Signature!', [
                'received'  => $receivedSignature,
                'calcRaw'   => $calcSignatureRaw,
                'calcInt'   => $calcSignatureInt,
                'orderId'   => $orderId,
                'amount'    => $amountRaw,
                'mCode'     => $merchantCode,
            ]);
            throw new InvalidArgumentException('Bad Signature', 400);
        }

        Log::info('DUITKU CALLBACK: Signature Valid.');
    }

    /**
     * Memperbarui status Payment, BookingDetail, dan membersihkan cache jadwal slot lapangan.
     */
    private function updatePaymentAndBookingStates(Payment $payment, array $payload): void
    {
        if ($payment->status !== PaymentStatus::PENDING->value) {
            Log::info("DUITKU CALLBACK: Diabaikan karena status payment sudah bukan PENDING (Status saat ini: {$payment->status}).");
            return;
        }

        $bookingId = explode('-', $payload['merchantOrderId'])[0];
        $paymentCode = $payload['paymentCode'] ?? 'transfer';

        DB::transaction(function () use ($payment, $payload, $paymentCode, $bookingId) {
            $booking = Booking::with('details')->find($bookingId);

            if ($payload['resultCode'] === self::CODE_DUITKU_SUCCESS) {
                // 1. Pembayaran Berhasil -> Update ke SUCCESS & Booking ACTIVE
                $payment->update([
                    'status'  => PaymentStatus::SUCCESS->value,
                    'method'  => $paymentCode,
                    'paid_at' => now(),
                ]);

                BookingDetail::query()->where('fk_booking_id', $bookingId)->update([
                    'status' => BookingDetailStatus::ACTIVE->value,
                ]);

                Log::info("DUITKU CALLBACK SUKSES: Booking ID {$bookingId} diupdate ke ACTIVE & Payment ke SUCCESS.");
            } else {
                // 2. Pembayaran Gagal / Expired -> Update ke FAILED & Booking CANCELLED (melepaskan slot)
                $payment->update([
                    'status' => PaymentStatus::FAILED->value,
                ]);

                BookingDetail::query()->where('fk_booking_id', $bookingId)->update([
                    'status' => BookingDetailStatus::CANCELLED->value,
                ]);

                Log::info("DUITKU CALLBACK GAGAL/KEDALUWARSA: Booking ID {$bookingId} diupdate ke CANCELLED & Payment ke FAILED.");
            }

            // 3. Bersihkan cache jadwal slot lapangan agar kalender langsung menampilkan status terbaru
            if ($booking) {
                $fieldId = $booking->fk_field_id;
                Cache::forget("tenant_nearest_bookings_field_{$fieldId}");

                foreach ($booking->details as $detail) {
                    if (!empty($detail->play_date)) {
                        Cache::forget("tenant_slots_field_{$fieldId}_{$detail->play_date}");
                    }
                }
                Log::info("DUITKU CALLBACK: Cache slot jadwal untuk lapangan {$fieldId} berhasil dibersihkan.");
            }
        });
    }
}
