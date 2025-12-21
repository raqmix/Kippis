<?php

namespace App\Integrations\Foodics\DTOs;

class FoodicsPaginationDTO
{
    public function __construct(
        public FoodicsPaginationLinksDTO $links,
        public FoodicsPaginationMetaDTO $meta,
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
            links: FoodicsPaginationLinksDTO::fromArray($data['links'] ?? []),
            meta: FoodicsPaginationMetaDTO::fromArray($data['meta'] ?? []),
        );
    }
    
    /**
     * Check if there is a next page.
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->meta->current_page < $this->meta->last_page;
    }
}

