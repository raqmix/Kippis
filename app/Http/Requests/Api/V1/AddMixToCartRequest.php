<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddMixToCartRequest extends FormRequest
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
            'item_type' => 'required|in:product,mix,creator_mix',
            'quantity' => 'required|integer|min:1',
            'configuration' => 'required_if:item_type,mix,creator_mix|array',
            'configuration.base_id' => 'nullable|exists:products,id',
            'configuration.base_price' => 'nullable|numeric|min:0',
            'configuration.builder_id' => 'nullable|integer',
            'configuration.mix_builder_id' => 'nullable|integer',
            'configuration.modifiers' => 'nullable|array',
            'configuration.modifiers.*.id' => 'required_with:configuration.modifiers|exists:modifiers,id',
            'configuration.modifiers.*.level' => 'nullable|integer|min:0',
            'configuration.extras' => 'nullable|array',
            'configuration.extras.*' => 'exists:products,id',
            'ref_id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'product_id' => 'required_if:item_type,product|exists:products,id',
            'note' => 'nullable|string|max:1000',
            'store_id' => 'nullable|exists:stores,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'configuration.extras.*.exists' => 'The selected extra must be a valid product ID. Extras must be product IDs, not modifier IDs.',
        ];
    }
}
