<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DuitkuService
{
    protected $merchantCode;
    protected $merchantKey;
    protected $sandboxMode;

    public function __construct()
    {
        $this->merchantCode = trim(env('DUITKU_MERCHANT_CODE', ''));
        $this->merchantKey  = trim(env('DUITKU_MERCHANT_KEY', ''));

        $sandboxRaw = env('DUITKU_SANDBOX_MODE', true);
        $this->sandboxMode  = filter_var($sandboxRaw, FILTER_VALIDATE_BOOLEAN);
    }

    public function createInvoice($booking, $amount)
    {
        $merchantOrderId = $booking->id . '-' . time();
        $amountInt = (int) round($amount);

        // Pastikan signature dibuat dengan urutan yang benar sesuai dokumentasi (Code + OrderId + Amount + Key)
        $rawSignatureString = $this->merchantCode . $merchantOrderId . $amountInt . $this->merchantKey;
        $signature = md5($rawSignatureString);

        $cleanPhone = preg_replace('/[^0-9]/', '', $booking->customer_phone ?? '');
        if (empty($cleanPhone)) {
            $cleanPhone = '08123456789';
        }

        $cleanEmail = filter_var($booking->customer_email, FILTER_VALIDATE_EMAIL)
            ? $booking->customer_email
            : 'customer@joglo66.com';

        $params = [
            'merchantCode'      => $this->merchantCode,
            'paymentAmount'     => $amountInt,
            'merchantOrderId'   => $merchantOrderId,
            'productDetails'    => 'Penyewaan Lapangan - ' . ($booking->team_name ?? 'Customer'),
            'email'             => $cleanEmail,
            'phoneNumber'       => $cleanPhone,
            'customerVaName'    => substr($booking->team_name ?? 'Customer', 0, 30),
            'callbackUrl'       => env('APP_URL') . '/api/duitku/callback',
            'returnUrl'         => route('tenant.booking.success', ['booking_id' => $booking->id]),
            'expiryPeriod'      => 60,
            'signature'         => $signature,
        ];

        // Tentukan URL 1 saja berdasarkan mode
        $url = $this->sandboxMode
            ? 'https://api-sandbox.duitku.com/api/merchant/createInvoice'
            : 'https://api-prod.duitku.com/api/merchant/createInvoice';

        $maskedMerchantKey = strlen($this->merchantKey) <= 8
            ? str_repeat('*', strlen($this->merchantKey))
            : substr($this->merchantKey, 0, 4) . str_repeat('*', max(4, strlen($this->merchantKey) - 8)) . substr($this->merchantKey, -4);

        Log::info("Konfigurasi Duitku", [
            'mode' => $this->sandboxMode ? 'sandbox' : 'production',
            'merchantCode' => $this->merchantCode,
            'merchantKeyMasked' => $maskedMerchantKey,
            'merchantKeyLength' => strlen($this->merchantKey),
            'merchantKeyFingerprint' => substr(hash('sha256', $this->merchantKey), 0, 12),
        ]);

        Log::info("Mencoba request Duitku ke URL: {$url}");

        $response = Http::withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($url, $params);

        $data = $response->json();

        if ($response->successful() && isset($data['statusCode']) && $data['statusCode'] === '00') {
            Log::info("Duitku Request Berhasil", ['response' => $data]);
            return (object) $data;
        }

        Log::warning("Duitku gagal [Status: " . $response->status() . "]", ['response' => $data]);

        $msg = $data['statusMessage'] ?? $data['Message'] ?? $response->body();
        throw new \Exception("Duitku Reject [HTTP " . $response->status() . "]: " . $msg);
    }
}
