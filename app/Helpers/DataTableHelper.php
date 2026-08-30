<?php

namespace App\Helpers;

use App\Data\DataTablePayloadData;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class DataTableHelper
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public static function parse(EloquentBuilder | QueryBuilder $query, DataTablePayloadData $payload): array
    {
        $original = $query->clone()->count();

        $data = $query
            ->when($payload->has_sort, function ($query) use ($payload): void {
                foreach ($payload->sort as $sort) {
                    $query->orderBy($sort->id, $sort->direction);
                }
            })
            ->when($payload->has_filters, function ($query) use ($payload): void {
                foreach ($payload->filters as $filter) {
                    $query->whereLike($filter->id, "%{$filter->value}%");
                }
            })
            ->paginate(perPage: $payload->rows_per_page, page: $payload->current_page);

        return [
            'data' => $data->items(),
            'meta' => [
                'total' => $original,
                'has_filter' => $payload->has_filters,
                'filter_quantity' => $original != $data->total() ? $data->total() : $original,
            ],
        ];
    }
}
