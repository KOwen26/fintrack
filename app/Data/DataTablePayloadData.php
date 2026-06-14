<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DataTablePayloadData extends Data
{
    public bool $has_sort = false;

    public bool $has_filters = false;

    public function __construct(
        public ?int $rows_per_page = 10,
        public ?int $current_page = 1,
        public ?array $sort = [],
        public ?array $filters = []
    ) {
        if (filled($this->sort)) {
            $this->has_sort = true;
        }

        if (filled($this->filters)) {
            $this->has_filters = true;
        }
    }

    public static function fromQueryParams(array $params): self
    {
        return new self(
            rows_per_page: $params['rows_per_page'] ?? 10,
            current_page: $params['current_page'] ?? 1,
            sort: isset($params['sort']) ? json_decode($params['sort'], false) : [],
            filters: isset($params['filters']) ? json_decode($params['filters'], false) : []
        );
    }
}
