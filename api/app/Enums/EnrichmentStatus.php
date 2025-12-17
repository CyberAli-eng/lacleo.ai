<?php

namespace App\Enums;

enum EnrichmentStatus: string
{
    case QUEUED = 'queued';
    case NOT_STARTED = 'not_started';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FAILED = 'failed';
    case COMPLETED = 'completed';

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED]);
    }
}
