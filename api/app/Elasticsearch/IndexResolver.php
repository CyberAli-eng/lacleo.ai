<?php

namespace App\Elasticsearch;

use InvalidArgumentException;

class IndexResolver
{
    public static function contacts(): string
    {
        return self::requireEnv('ELASTIC_CONTACT_INDEX');
    }

    public static function companies(): string
    {
        return self::requireEnv('ELASTIC_COMPANY_INDEX');
    }

    public static function all(): array
    {
        return [
            self::contacts(),
            self::companies(),
        ];
    }

    protected static function requireEnv(string $key): string
    {
        $val = env($key);
        if (!is_string($val) || $val === '') {
            // Allow missing env vars during CLI/Build phase to prevent crashing Docker build
            if (function_exists('app') && app()->runningInConsole() && !app()->isProduction()) {
                return (string) $val;
            }
            throw new \InvalidArgumentException("Missing required env: {$key}");
        }
        return $val;
    }
}
