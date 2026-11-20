<?php

namespace App\Http\Controllers\Tenant\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Payment\DuitkuCallbackRequest;
use App\Services\Tenant\Payment\TenantDuitkuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;
use Throwable;

class DuitkuController extends Controller
{
    protected TenantDuitkuService $duitkuService;

    public function __construct(TenantDuitkuService $duitkuService)
    {
        $this->duitkuService = $duitkuService;
    }

    /**
     * Endpoint Webhook / Callback dari Duitku.
     * Menerima notifikasi perubahan status pembayaran dari Duitku.
     */
    public function callback(DuitkuCallbackRequest $request): JsonResponse
    {
        $statusCode = 200;
        $data = ['status' => 'Success', 'message' => 'Callback berhasil diproses'];

        try {
            $this->duitkuService->processWebhookCallback($request->all());
        } catch (InvalidArgumentException $e) {
            $statusCode = $e->getCode() ?: 400;
            $data = ['status' => 'Bad Request', 'message' => $e->getMessage()];
            Log::warning('DUITKU CALLBACK Gagal (Bad Request): ' . $e->getMessage());
        } catch (UnexpectedValueException $e) {
            $statusCode = $e->getCode() ?: 404;
            $data = ['status' => 'Not Found', 'message' => $e->getMessage()];
            Log::warning('DUITKU CALLBACK Gagal (Not Found): ' . $e->getMessage());
        } catch (Throwable $e) {
            $statusCode = 500;
            $data = ['status' => 'Internal Server Error', 'message' => 'Terjadi kesalahan pada server.'];
            Log::error('DUITKU CALLBACK Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json($data, $statusCode);
    }
}
