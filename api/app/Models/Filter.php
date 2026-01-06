<?php

namespace App\Models;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class Filter implements Arrayable
{
    public $filter_id;
    public $name;
    public $group;
    public $value_source;
    public $value_type;
    public $input_type;
    public $is_searchable;
    public $allows_exclusion;
    public $settings = [];
    public $sort_order;
    public $is_active;
    public $type;
    public $filter_type;
    public $supports_value_lookup;
    public $range;
    // Fallback property used in handlers if settings['fields'] is missing
    public $elasticsearch_field;

    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray()
    {
        return get_object_vars($this);
    }

    public function getTargetModelOrFail()
    {
        $modelClass = $this->settings['target_model'] ?? null;

        if (!$modelClass || !class_exists($modelClass)) {
            // If we have a filter_id, use it for the error message, otherwise generic
            $id = $this->filter_id ?? 'unknown';
            throw new InvalidArgumentException(
                "Invalid or missing target model for filter: {$id}"
            );
        }

        return $modelClass;
    }
}