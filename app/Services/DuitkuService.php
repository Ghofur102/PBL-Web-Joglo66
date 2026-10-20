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
        $this->merchantCode = trim(config('services.duitku.merchant_code') ?? env('DUITKU_MERCHANT_CODE', ''));
        $this->merchantKey  = trim(config('services.duitku.merchant_key') ?? env('DUITKU_MERCHANT_KEY', ''));

        $sandboxRaw = config('services.duitku.sandbox_mode') ?? env('DUITKU_SANDBOX_MODE', true);
        $this->sandboxMode  = filter_var($sandboxRaw, FILTER_VALIDATE_BOOLEAN);
    }

    public function createInvoice($booking, $amount)
    {
        $merchantOrderId = $booking->id . '-' . time();
        $amountInt = (int) round($amount);

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

        // Daftar URL endpoint Sandbox yang akan dicoba secara berurutan
        $endpoints = $this->sandboxMode ? [
            'https://api-sandbox.duitku.com/api/merchant/createInvoice',
            'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry',
            'https://api-sandbox.duitku.com/api/merchant/v2/inquiry',
        ] : [
            'https://api-prod.duitku.com/api/merchant/createInvoice',
            'https://passport.duitku.com/webapi/api/merchant/v2/inquiry',
        ];

        $lastResponse = null;
        foreach ($endpoints as $url) {
            Log::info("Mencoba request Duitku ke URL: {$url}");

            $response = Http::withHeaders([
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($url, $params);

            $lastResponse = $response;
            $data = $response->json();

            if ($response->successful() && isset($data['statusCode']) && $data['statusCode'] === '00') {
                Log::info("Duitku Request Berhasil via URL: {$url}", ['response' => $data]);
                return (object) $data;
            }

            Log::warning("Duitku URL {$url} gagal [Status: " . $response->status() . "]", ['response' => $data]);
        }

        $data = $lastResponse ? $lastResponse->json() : [];
        $msg = $data['statusMessage'] ?? $data['Message'] ?? ($lastResponse ? $lastResponse->body() : 'No response');
        throw new \Exception("Duitku Reject [HTTP " . ($lastResponse ? $lastResponse->status() : '0') . "]: " . $msg);
    }
}
