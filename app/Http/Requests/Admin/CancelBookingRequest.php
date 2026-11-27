<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string'],
            'status_refund' => ['required', 'in:Full,Partial,None'],
            'refund_amount' => ['required', 'integer', 'min:0'],
            'refund_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
