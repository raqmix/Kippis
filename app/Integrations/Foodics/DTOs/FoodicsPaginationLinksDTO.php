<?php

namespace App\Integrations\Foodics\DTOs;

class FoodicsPaginationLinksDTO
{
    public function __construct(
        public ?string $first = null,
        public ?string $last = null,
        public ?string $prev = null,
        public ?string $next = null,
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
            first: $data['first'] ?? null,
            last: $data['last'] ?? null,
            prev: $data['prev'] ?? null,
            next: $data['next'] ?? null,
        );
    }
}

