<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationFilter extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LocationFilter::class, 'parent_value_id', 'value_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(LocationFilter::class, 'parent_value_id', 'value_id');
    }
}
