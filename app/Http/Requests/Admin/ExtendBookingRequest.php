<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExtendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fk_booking_detail_id' => 'required|integer|exists:booking_details,id',
            'extend_minutes'       => 'nullable|integer|in:30,60,90,120',
        ];
    }
}
