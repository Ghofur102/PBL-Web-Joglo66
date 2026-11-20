<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DuitkuService
{
    protected string $merchantCode;
    protected string $apiKey;
    protected bool $sandboxMode;

    public function __construct()
    {
        $this->merchantCode = trim((string) (config('duitku.merchant_code') ?: config('services.duitku.merchant_code') ?: env('DUITKU_MERCHANT_CODE', '')));
        $this->apiKey       = trim((string) (config('duitku.api_key') ?: config('services.duitku.merchant_key') ?: env('DUITKU_API_KEY', env('DUITKU_MERCHANT_KEY', ''))));

        $sandboxRaw = config('duitku.sandbox', config('services.duitku.sandbox_mode', env('DUITKU_SANDBOX', true)));
        $this->sandboxMode  = filter_var($sandboxRaw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Membuat invoice pembayaran menggunakan Duitku POP (Hosted Payment Checkout).
     *
     * @param  mixed  $booking
     * @param  int|float  $amount
     * @return object
     * @throws Exception
     */
    public function createInvoice($booking, $amount): object
    {
        if (empty($this->merchantCode) || empty($this->apiKey)) {
            Log::error('Duitku Error: DUITKU_MERCHANT_CODE atau DUITKU_API_KEY belum dikonfigurasi di file .env.');
            throw new Exception('Konfigurasi Duitku belum lengkap. Pastikan DUITKU_MERCHANT_CODE dan DUITKU_API_KEY sudah diisi di file .env.');
        }

        $merchantOrderId = $booking->id . '-' . time();
        $amountInt = (int) round($amount);

        // Validasi nomor telepon dan email
        $cleanPhone = preg_replace('/[^0-9]/', '', $booking->customer_phone ?? '');
        if (empty($cleanPhone) || strlen($cleanPhone) < 8) {
            $cleanPhone = '08123456789';
        }

        $cleanEmail = filter_var($booking->customer_email, FILTER_VALIDATE_EMAIL)
            ? $booking->customer_email
            : 'customer@joglo66.com';

        $productDetails = 'Penyewaan Lapangan - ' . ($booking->team_name ?? 'Customer');
        $customerVaName = substr($booking->team_name ?? 'Customer', 0, 30);

        // URL Callback & Return
        $callbackUrl = config('duitku.callback_url') ?: url('/api/duitku/callback');
        $returnUrl   = config('duitku.return_url') ?: route('tenant.booking.success', ['booking_id' => $booking->id]);
        $expiryPeriod = (int) config('duitku.expiry_period', 60);

        $itemDetails = [
            [
                'name'     => substr($productDetails, 0, 50),
                'price'    => $amountInt,
                'quantity' => 1,
            ],
        ];

        $payload = [
            'paymentAmount'   => $amountInt,
            'merchantOrderId' => $merchantOrderId,
            'productDetails'  => $productDetails,
            'email'           => $cleanEmail,
            'phoneNumber'     => $cleanPhone,
            'customerVaName'  => $customerVaName,
            'callbackUrl'     => $callbackUrl,
            'returnUrl'       => $returnUrl,
            'expiryPeriod'    => $expiryPeriod,
            'itemDetails'     => $itemDetails,
        ];

        // Format Autentikasi Duitku POP API
        // Endpoint: https://api-sandbox.duitku.com/api/merchant/createInvoice (Sandbox)
        // Header Signature: SHA256(merchantCode + timestamp + apiKey)
        $timestamp = (string) round(microtime(true) * 1000);
        $signature = hash('sha256', $this->merchantCode . $timestamp . $this->apiKey);

        $baseUrl = $this->sandboxMode ? 'https://api-sandbox.duitku.com' : 'https://api-prod.duitku.com';
        $url = $baseUrl . '/api/merchant/createInvoice';

        $headers = [
            'x-duitku-signature'    => $signature,
            'x-duitku-timestamp'    => $timestamp,
            'x-duitku-merchantcode' => $this->merchantCode,
            'Accept'                => 'application/json',
            'Content-Type'          => 'application/json',
        ];

        Log::info('Duitku POP createInvoice: Mengirim request ke Duitku', [
            'url'              => $url,
            'merchantCode'     => $this->merchantCode,
            'merchantOrderId'  => $merchantOrderId,
            'amount'           => $amountInt,
            'timestamp'        => $timestamp,
            'callbackUrl'      => $callbackUrl,
            'returnUrl'        => $returnUrl,
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $payload);

            $data = $response->json();

            Log::info('Duitku POP createInvoice: Response diterima', [
                'http_status' => $response->status(),
                'response'    => $data ?? $response->body(),
            ]);

            if ($response->successful() && isset($data['statusCode']) && $data['statusCode'] === '00') {
                return (object) [
                    'statusCode'    => $data['statusCode'],
                    'statusMessage' => $data['statusMessage'] ?? 'SUCCESS',
                    'reference'     => $data['reference'] ?? null,
                    'paymentUrl'    => $data['paymentUrl'] ?? null,
                    'vaNumber'      => $data['vaNumber'] ?? null,
                    'qrString'      => $data['qrString'] ?? null,
                ];
            }

            // Jika status code bukan 00 atau HTTP gagal
            $errorMessage = $data['statusMessage'] ?? $data['Message'] ?? $response->body();
            Log::error('Duitku POP createInvoice: Duitku me-reject request', [
                'http_status' => $response->status(),
                'error'       => $errorMessage,
                'raw_body'    => $response->body(),
            ]);

            throw new Exception("Duitku Reject [HTTP {$response->status()}]: {$errorMessage}");
        } catch (Exception $e) {
            Log::error('Duitku POP createInvoice: Exception saat request', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
