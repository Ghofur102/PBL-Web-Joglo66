<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100', 'not_regex:/<\s*script\b/i'],
            'type' => 'sometimes|string|max:50',
            'stock' => 'sometimes|integer|min:0',
            'price_hour' => 'sometimes|integer|min:0',
            'status' => ['sometimes', new Enum(GeneralStatus::class)],
        ];
    }
}
