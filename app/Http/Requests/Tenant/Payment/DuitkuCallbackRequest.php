<?php

namespace App\Http\Requests\Tenant\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DuitkuCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Catat log request mentah sebelum validasi berjalan.
     */
    protected function prepareForValidation(): void
    {
        Log::info('--- DUITKU CALLBACK: Request Masuk ---', [
            'method'  => $this->method(),
            'url'     => $this->fullUrl(),
            'payload' => $this->all(),
            'ip'      => $this->ip(),
        ]);
    }

    public function rules(): array
    {
        return [
            'merchantOrderId' => ['required', 'string'],
            'reference'       => ['nullable', 'string'],
            'signature'       => ['required', 'string'],
            'amount'          => ['required'],
            'resultCode'      => ['required', 'string'],
            'merchantCode'    => ['nullable', 'string'],
            'paymentCode'     => ['nullable', 'string'],
            'spUserHash'      => ['nullable', 'string'],
            'additionalParam' => ['nullable', 'string'],
        ];
    }

    /**
     * Tangani kegagalan validasi dengan logging rinci dan HTTP 400 JSON.
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('--- DUITKU CALLBACK: Validasi Form Gagal ---', [
            'errors'  => $validator->errors()->toArray(),
            'payload' => $this->all(),
        ]);

        throw new HttpResponseException(response()->json([
            'status'  => 'Bad Request',
            'message' => 'Parameter callback tidak valid.',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
