<?php

namespace App\Filters;

use App\Filters\Contracts\FilterHandlerInterface;
use App\Models\Filter;
use InvalidArgumentException;

class FilterHandlerFactory
{
    public function make(Filter $filter): FilterHandlerInterface
    {
        // Use 'type' from registry
        $type = $filter->type ?? null;
        
        return match ($type) {
            'text' => new \App\Filters\Handlers\TextFilterHandler($filter),
            'keyword' => new \App\Filters\Handlers\FacetFilterHandler($filter),
            'range', 'date' => new \App\Filters\Handlers\RangeFilterHandler($filter),
            'boolean' => new \App\Filters\Handlers\BooleanFilterHandler($filter),
            'direct' => new \App\Filters\Handlers\DirectFilterHandler($filter),
            default => new \App\Filters\Handlers\ElasticsearchFilterHandler($filter)
        };
    }
}