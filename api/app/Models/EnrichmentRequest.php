<?php

namespace App\Models;

use App\Enums\EnrichmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrichmentRequest extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => EnrichmentStatus::class,
        'request_data' => 'array',
        'retry_count' => 'integer',
        'last_processed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => EnrichmentStatus::QUEUED,
        'retry_count' => 0,
        'last_processed_at' => null,
    ];

    public function result()
    {
        return $this->hasOne(EnrichmentResult::class);
    }

    public function updateStatus(EnrichmentStatus $status, ?string $errorMessage = null)
    {
        $this->update([
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
