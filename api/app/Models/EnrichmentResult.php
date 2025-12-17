<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrichmentResult extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'raw_response' => 'array',
    ];

    public function enrichRequest()
    {
        return $this->belongsTo(EnrichmentRequest::class);
    }
}
