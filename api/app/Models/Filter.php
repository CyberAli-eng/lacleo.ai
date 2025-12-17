<?php

namespace App\Models;

use App\Casts\ClassCast;
use App\Filters\FilterHandlerFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Filter extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'settings.target_model' => ClassCast::class,
        'is_searchable' => 'boolean',
        'allows_exclusion' => 'boolean',
        'supports_value_lookup' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'handler_class' => ClassCast::class,
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function filterGroup(): BelongsTo
    {
        return $this->belongsTo(FilterGroup::class, 'filter_group_id');
    }

    public function filterValues(): HasMany
    {
        return $this->hasMany(FilterValue::class);
    }

    public function getTargetModelOrFail()
    {
        $modelClass = $this->settings['target_model'] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            throw new InvalidArgumentException(
                "Invalid or missing target model for filter: {$this->filter->filter_id}"
            );
        }

        return $modelClass;
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModel(Builder $query, string $modelClass)
    {
        return $query->whereJsonContains('settings->target_model', $modelClass);
    }

    public function getHandler()
    {
        return app(FilterHandlerFactory::class)->make($this);
    }
}
