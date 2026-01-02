<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Log;

trait SanitizesPII
{
    /**
     * Sanitize PII from log data
     */
    protected function sanitizeForLog(array $data): array
    {
        $piiFields = [
            'email',
            'emails',
            'phone',
            'phone_number',
            'mobile_number',
            'direct_number',
            'password',
            'token',
            'api_key',
            'credit_card',
            'ssn',
            'first_name',
            'last_name',
            'full_name',
            'address',
            'linkedin_url',
        ];

        return $this->recursiveSanitize($data, $piiFields);
    }

    /**
     * Recursively sanitize PII fields
     */
    private function recursiveSanitize(array $data, array $piiFields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $piiFields);
            } elseif (in_array($key, $piiFields, true)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Log with PII sanitization
     */
    protected function logSanitized(string $level, string $message, array $context = []): void
    {
        $sanitizedContext = $this->sanitizeForLog($context);
        Log::log($level, $message, $sanitizedContext);
    }
}
