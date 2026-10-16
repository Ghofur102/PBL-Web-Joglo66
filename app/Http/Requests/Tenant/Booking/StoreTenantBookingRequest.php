<?php

namespace App\Http\Requests\Tenant\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field_id' => ['required', 'exists:mysql_joglo66_app.fields,id'],
            'team_name' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'booking_data' => ['required', 'json'],
            'payment_type' => ['required', 'in:down payment,final payment'],
            'refund_terms_accepted' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_terms_accepted.required' => 'Anda harus menyetujui syarat dan ketentuan refund sebelum melanjutkan.',
            'refund_terms_accepted.accepted' => 'Anda harus menyetujui syarat dan ketentuan refund sebelum melanjutkan.',
        ];
    }
}
