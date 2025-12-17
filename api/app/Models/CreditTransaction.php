<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'amount',
        'type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
