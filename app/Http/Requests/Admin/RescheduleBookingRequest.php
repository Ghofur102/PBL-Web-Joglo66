<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_play_date' => ['required', 'date'],
            'new_start_time' => ['required', 'date_format:H:i'],
            'new_end_time' => ['required', 'date_format:H:i', 'after:new_start_time'],
            'reason' => ['required', 'string'],
            'new_price' => ['required', 'integer', 'min:0'],
            'financial_action' => ['required', 'in:Lunas,Settle_Later'],
            'reconciled_amount' => ['nullable', 'integer', 'min:0'],
            'refund_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
