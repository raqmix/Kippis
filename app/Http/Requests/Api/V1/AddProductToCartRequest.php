<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddProductToCartRequest extends FormRequest
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
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'addons' => 'nullable|array',
            'addons.*.modifier_id' => 'required_with:addons|exists:modifiers,id',
            'addons.*.id' => 'required_without:addons.*.modifier_id|exists:modifiers,id',
            'addons.*.level' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize addons: if 'id' is provided instead of 'modifier_id', use it
        if ($this->has('addons') && is_array($this->addons)) {
            $this->merge([
                'addons' => array_map(function ($addon) {
                    if (isset($addon['id']) && !isset($addon['modifier_id'])) {
                        $addon['modifier_id'] = $addon['id'];
                    }
                    return $addon;
                }, $this->addons),
            ]);
        }
    }
}
