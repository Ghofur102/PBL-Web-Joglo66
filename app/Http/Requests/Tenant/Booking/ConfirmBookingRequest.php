<?php

namespace App\Http\Requests\Tenant\Booking;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field_id'       => ['required', 'exists:mysql_joglo66_app.fields,id'],
            'selected_slots' => ['required', 'json', 'string'],
        ];
    }
}
