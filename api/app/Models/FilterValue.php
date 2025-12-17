<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilterValue extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Get the last inserted ID and add 1
            $lastId = static::max('id');
            $model->order = ($lastId ?? 0) + 1;
        });
    }

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function filter(): BelongsTo
    {
        return $this->belongsTo(Filter::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query)
    {
        return $query->orderBy('order');
    }
}
