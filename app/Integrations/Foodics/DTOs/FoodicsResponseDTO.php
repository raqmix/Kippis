<?php

namespace App\Integrations\Foodics\DTOs;

class FoodicsResponseDTO
{
    public function __construct(
        public bool $ok,
        public int $status_code,
        public ?array $data = null,
        public ?FoodicsErrorDTO $error = null,
        public ?FoodicsPaginationDTO $pagination = null,
    ) {
    }
    
    /**
     * Create success response.
     *
     * @param array $data
     * @param int $statusCode
     * @param array|null $paginationData
     * @return self
     */
    public static function success(array $data, int $statusCode = 200, ?array $paginationData = null): self
    {
        $pagination = null;
        if ($paginationData && isset($paginationData['links']) && isset($paginationData['meta'])) {
            $pagination = FoodicsPaginationDTO::fromArray($paginationData);
        }
        
        return new self(
            ok: true,
            status_code: $statusCode,
            data: $data,
            pagination: $pagination,
        );
    }
    
    /**
     * Create error response.
     *
     * @param FoodicsErrorDTO $error
     * @param int $statusCode
     * @return self
     */
    public static function error(FoodicsErrorDTO $error, int $statusCode = 400): self
    {
        return new self(
            ok: false,
            status_code: $statusCode,
            error: $error,
        );
    }
}

