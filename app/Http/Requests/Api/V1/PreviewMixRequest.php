<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\ExtraItemRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewMixRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'configuration' => 'required|array',
            'configuration.base_id' => 'nullable|exists:products,id',
            'configuration.base_price' => 'nullable|numeric|min:0',
            'configuration.builder_id' => 'nullable|integer',
            'configuration.mix_builder_id' => 'nullable|integer',
            'configuration.modifiers' => 'nullable|array',
            'configuration.modifiers.*.id' => 'required_with:configuration.modifiers|exists:modifiers,id',
            'configuration.modifiers.*.level' => 'nullable|integer|min:0',
            'configuration.extras' => 'nullable|array',
            'configuration.extras.*' => [new ExtraItemRule()],
        ];
    }
}
