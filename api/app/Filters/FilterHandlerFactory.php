<?php

namespace App\Filters;

use App\Filters\Contracts\FilterHandlerInterface;
use App\Filters\Handlers\DirectInputFilterHandler;
use App\Filters\Handlers\ElasticsearchFilterHandler;
use App\Filters\Handlers\LocationFilterHandler;
use App\Filters\Handlers\PredefinedFilterHandler;
use App\Models\Filter;
use InvalidArgumentException;

class FilterHandlerFactory
{
    public function make(Filter $filter): FilterHandlerInterface
    {
        return match ($filter->value_source) {
            'direct' => new DirectInputFilterHandler($filter),
            'predefined' => new PredefinedFilterHandler($filter),
            'elasticsearch' => new ElasticsearchFilterHandler($filter),
            'specialized' => $this->makeSpecializedHandler($filter),
            default => throw new InvalidArgumentException("Unsupported value source: {$filter->value_source}")
        };
    }

    protected function makeSpecializedHandler(Filter $filter): FilterHandlerInterface
    {
        return match ($filter->value_type) {
            'location' => new LocationFilterHandler($filter),
            default => throw new InvalidArgumentException(
                "Unsupported specialized filter type: {$filter->value_type}"
            )
        };
    }
}
