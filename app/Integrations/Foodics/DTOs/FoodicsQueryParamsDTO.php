<?php

namespace App\Integrations\Foodics\DTOs;

class FoodicsQueryParamsDTO
{
    public function __construct(
        public ?int $page = null,
        public array $include = [],
        public array $filters = [],
        public ?string $sort = null,
    ) {
    }
    
    /**
     * Convert to query array for HTTP client.
     *
     * @return array
     */
    public function toQuery(): array
    {
        $query = [];
        
        // Pagination
        if ($this->page !== null) {
            $query['page'] = $this->page;
        }
        
        // Includes
        if (!empty($this->include)) {
            $query['include'] = implode(',', $this->include);
        }
        
        // Filters
        foreach ($this->filters as $key => $value) {
            $query["filter[{$key}]"] = $value;
        }
        
        // Sort
        if ($this->sort !== null) {
            $query['sort'] = $this->sort;
        }
        
        return $query;
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
            page: $data['page'] ?? null,
            include: $data['include'] ?? [],
            filters: $data['filters'] ?? [],
            sort: $data['sort'] ?? null,
        );
    }
}

