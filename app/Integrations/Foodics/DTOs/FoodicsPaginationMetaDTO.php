<?php

namespace App\Integrations\Foodics\DTOs;

class FoodicsPaginationMetaDTO
{
    public function __construct(
        public int $current_page,
        public int $from,
        public int $last_page,
        public string $path,
        public int $per_page,
        public int $to,
        public int $total,
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
            current_page: $data['current_page'] ?? 1,
            from: $data['from'] ?? 0,
            last_page: $data['last_page'] ?? 1,
            path: $data['path'] ?? '',
            per_page: $data['per_page'] ?? 15,
            to: $data['to'] ?? 0,
            total: $data['total'] ?? 0,
        );
    }
}

