<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilterRegistry extends Model
{
    protected $table = 'filter_registries';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'label',
        'group_name',
        'applies_to',
        'type',
        'input_type',
        'data_source',
        'fields',
        'search_config',
        'filtering_config',
        'aggregation_config',
        'preloaded_values',
        'range_config',
        'additional_settings',
        'hint',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'applies_to' => 'array',
        'fields' => 'array',
        'search_config' => 'array',
        'filtering_config' => 'array',
        'aggregation_config' => 'array',
        'preloaded_values' => 'array',
        'range_config' => 'array',
        'additional_settings' => 'array',
        'is_active' => 'boolean',
    ];
}
