<?php

namespace App\Rules;

use App\Core\Models\Modifier;
use App\Core\Models\Product;
use Illuminate\Contracts\Validation\Rule;

class ExtraItemRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $id = (int) $value;

        // Check if it's a valid product ID
        if (Product::where('id', $id)->exists()) {
            return true;
        }

        // Check if it's a valid modifier ID with type "extra"
        if (Modifier::where('id', $id)->where('type', 'extra')->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The selected extra must be a valid product ID or a modifier ID with type "extra".';
    }
}

