<?php

namespace App\Http\Middleware;

class RedactSensitiveMiddleware
{
    public static function redactArray(array $data): array
    {
        $keys = ['email', 'emails', 'phone', 'phones', 'ssn', 'address', 'dob', 'token', 'authorization', 'password', 'cookie'];
        $out = [];
        foreach ($data as $k => $v) {
            $key = strtolower((string) $k);
            if (in_array($key, $keys, true)) {
                $out[$k] = '[REDACTED]';

                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::redactArray($v);
            } elseif (is_string($v)) {
                $out[$k] = self::redactString($v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    public static function redactString(string $s): string
    {
        $t = $s;
        $t = preg_replace('/([a-z0-9._%+-]{1})[a-z0-9._%+-]*(@[a-z0-9.-]+\.[a-z]{2,})/i', '$1***$2', $t);
        $t = preg_replace('/\+?\d[\d\s\-().]{6,}\d/', '+X *** *** ****', $t);

        return $t ?? '';
    }
}
