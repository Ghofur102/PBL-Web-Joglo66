<?php

namespace App\Http\Requests\Owner;

use App\Enums\UserRole;
use App\Http\Requests\Concerns\ValidatesRefundRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OwnerFieldRequest extends FormRequest
{
    use ValidatesRefundRules;

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::OWNER->value;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRefundRulesInput();
    }

    public function rules(): array
    {
        return array_merge([
            'name' => 'required|string|max:50',
            'category' => 'required|in:futsal,mini soccer',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:255',
            'min_cancel_days' => 'nullable|integer|min:0|max:30',
            'min_reschedule_days' => 'nullable|integer|min:0|max:30',
            'max_reschedule_times' => 'nullable|integer|min:1|max:10',
        ], $this->refundRulesValidationRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateRefundRuleTiers($validator);
        });
    }
}
