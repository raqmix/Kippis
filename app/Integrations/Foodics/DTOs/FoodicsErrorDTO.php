<?php

namespace App\Integrations\Foodics\DTOs;

class FoodicsErrorDTO
{
    public function __construct(
        public string $code,
        public string $message,
        public ?array $raw = null,
    ) {
    }
    
    /**
     * Create from array.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? 'UNKNOWN_ERROR',
            message: $data['message'] ?? 'Unknown error',
            raw: $data,
        );
    }
}

