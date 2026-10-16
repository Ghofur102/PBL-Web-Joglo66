<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesRefundRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFieldRequest extends FormRequest
{
    use ValidatesRefundRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'id' => 'required|exists:fields,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|string|max:100',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'pricing_rules' => 'sometimes',
        ], $this->refundRulesValidationRules());
    }

    protected function prepareForValidation(): void
    {
        $this->prepareRefundRulesInput();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateRefundRuleTiers($validator);
        });
    }
}
