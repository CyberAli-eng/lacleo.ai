<?php

namespace App\Filters;

use App\Filters\Contracts\FilterHandlerInterface;
use App\Models\Filter;
use InvalidArgumentException;

class FilterHandlerFactory
{
    public function make(Filter $filter, ?FilterManager $manager = null): FilterHandlerInterface
    {
        // Use 'type' from registry
        $type = $filter->type ?? null;
        $source = $filter->value_source ?? null;

        if ($source === 'elasticsearch') {
            return new \App\Filters\Handlers\ElasticsearchFilterHandler($filter, $manager);
        }

        $mode = $filter->settings['filtering']['mode'] ?? null;
        if ($mode === 'exists') {
            return new \App\Filters\Handlers\ElasticsearchFilterHandler($filter, $manager);
        }
        return match ($type) {
            'text', 'keyword' => new \App\Filters\Handlers\TextFilterHandler($filter, $manager),
            'range', 'date' => new \App\Filters\Handlers\RangeFilterHandler($filter, $manager),
            'boolean' => new \App\Filters\Handlers\BooleanFilterHandler($filter, $manager),
            'direct' => new \App\Filters\Handlers\DirectFilterHandler($filter, $manager),
            default => new \App\Filters\Handlers\ElasticsearchFilterHandler($filter, $manager)
        };
    }
}
