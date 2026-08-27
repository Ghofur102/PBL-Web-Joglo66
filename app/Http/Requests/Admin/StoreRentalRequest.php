<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fk_booking_detail_id'    => 'required|integer|exists:booking_details,id',
            'items'                    => 'required|array|min:1',
            'items.*.fk_attribute_id' => 'required|integer|exists:attributes,id',
            'items.*.quantity'        => 'required|integer|min:1',
            'customer_name'           => 'required|string|max:100',
            'customer_phone'          => 'nullable|string|max:20',
            'duration_hours'          => 'required|integer|min:1',
            'transaction_date'        => 'required|date_format:Y-m-d',
        ];
    }
}
