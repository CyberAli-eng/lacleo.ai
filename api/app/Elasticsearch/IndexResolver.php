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
        // 🔒 ABSOLUTE BUILD SAFETY
        // Returning empty string instead of throwing during build-time (package:discover)
        if (function_exists('app') && app()->runningInConsole()) {
            return (string) env($key, '');
        }

        $val = env($key);
        if (!is_string($val) || $val === '') {
            throw new \InvalidArgumentException("Missing required env: {$key}");
        }
        return $val;
    }
}
