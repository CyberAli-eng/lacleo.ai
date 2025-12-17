<?php

namespace App\Utilities;

class SearchUrlParser
{
    private const VALID_DIRECTIONS = ['asc', 'desc'];

    private const DEFAULT_DIRECTION = 'asc';

    private const DEFAULT_SORT = '_id';

    public static function parseQuery($queryString)
    {
        $result = [
            'queryParams' => [],
            'variables' => [],
            'sort' => [],
        ];

        if (empty($queryString)) {
            return $result;
        }

        parse_str($queryString, $queryParams);

        if (isset($queryParams['sort'])) {
            $result['sort'] = self::parseSortParameter($queryParams['sort']);
            unset($queryParams['sort']);
        }

        foreach ($queryParams as $key => $value) {
            if ($key === 'query') {
                $result['variables'] = self::parseVoyagerOrJson($value);
            } else {
                $result['queryParams'][$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Robustly decode the search variables payload, supporting both the legacy
     * Voyager-style encoding and a plain JSON object.
     */
    private static function parseVoyagerOrJson($value): array
    {
        if (is_string($value)) {
            $trimmed = ltrim($value);

            // If the client already sent JSON, prefer that directly.
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $parsed = self::parseVoyagerString($value);

        // Fallback: if Voyager parsing produced nothing, attempt a JSON decode
        // after URL decoding the raw string.
        if (empty($parsed) && is_string($value)) {
            $decodedJson = json_decode(urldecode($value), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
                return $decodedJson;
            }
        }

        return $parsed;
    }

    private static function parseSortParameter($sortString): array
    {
        $sorts = [];
        // Accept either comma-delimited string (e.g., "field:asc,other:desc")
        // or array syntax like sort[0][field]=...&sort[0][direction]=...
        if (is_array($sortString)) {
            foreach ($sortString as $entry) {
                if (is_array($entry)) {
                    $field = (string) ($entry['field'] ?? self::DEFAULT_SORT);
                    $direction = self::normalizeSortDirection((string) ($entry['direction'] ?? self::DEFAULT_DIRECTION));
                    $sorts[] = [
                        'field' => $field,
                        'direction' => $direction,
                    ];
                } elseif (is_string($entry) && $entry !== '') {
                    $parts = explode(':', $entry);
                    $field = $parts[0];
                    $direction = isset($parts[1]) ? self::normalizeSortDirection($parts[1]) : self::DEFAULT_DIRECTION;
                    $sorts[] = [
                        'field' => $field,
                        'direction' => $direction,
                    ];
                }
            }

            return $sorts;
        }
        $sortCriteria = explode(',', (string) $sortString);

        foreach ($sortCriteria as $criteria) {
            $parts = explode(':', $criteria);
            $field = $parts[0];
            $direction = isset($parts[1]) ? self::normalizeSortDirection($parts[1]) : self::DEFAULT_DIRECTION;

            $sorts[] = [
                'field' => $field,
                'direction' => $direction,
            ];
        }

        return $sorts;
    }

    private static function normalizeSortDirection($direction)
    {
        $direction = strtolower(trim($direction));

        return in_array($direction, self::VALID_DIRECTIONS) ? $direction : self::DEFAULT_DIRECTION;
    }

    public static function parseVoyagerString($voyagerString)
    {
        $result = [];

        // parse_str() already URL decodes the query string, so we should NOT decode again
        // However, if the value comes from a different source (like direct JSON), it might still be encoded
        // Check if it looks URL encoded (contains %XX patterns) and decode only if needed
        $decodedString = $voyagerString;
        if (is_string($voyagerString) && preg_match('/%[0-9A-Fa-f]{2}/', $voyagerString)) {
            // String appears to be URL encoded, decode it
            $decodedString = urldecode($voyagerString);
        }

        // Check if string starts and ends with parentheses
        if (! preg_match('/^\((.*)\)$/', $decodedString, $matches)) {
            return $result;
        }

        // Get content within outer parentheses
        $content = $matches[1];

        // Split into key-value pairs
        $pairs = self::splitVoyagerPairs($content);

        foreach ($pairs as $pair) {
            if (strpos($pair, ':') !== false) {
                [$keyRaw, $value] = explode(':', $pair, 2);
                // Decode the key if it was encoded
                $key = preg_match('/%[0-9A-Fa-f]{2}/', $keyRaw) ? urldecode($keyRaw) : $keyRaw;
                $result[$key] = self::parseVoyagerValue($value);
            }
        }

        return $result;
    }

    private static function splitVoyagerPairs($string)
    {
        $pairs = [];
        $currentPair = '';
        $parenCount = 0;
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];

            if ($char === '(') {
                $parenCount++;
            } elseif ($char === ')') {
                $parenCount--;
            }

            // Only split on commas at the top level (parenCount == 0)
            if ($char === ',' && $parenCount === 0) {
                if (! empty($currentPair)) {
                    $pairs[] = trim($currentPair);
                }
                $currentPair = '';

                continue;
            }

            $currentPair .= $char;
        }

        // Don't forget the last pair
        if (! empty($currentPair)) {
            $pairs[] = trim($currentPair);
        }

        return $pairs;
    }

    private static function parseVoyagerValue($value)
    {
        // Decode URL-encoded value if needed (but only if it contains encoded characters)
        $decodedValue = $value;
        if (is_string($value) && preg_match('/%[0-9A-Fa-f]{2}/', $value)) {
            $decodedValue = urldecode($value);
        }

        // Treat empty parentheses as empty structure
        if (is_string($decodedValue) && preg_match('/^\(\s*\)$/', $decodedValue)) {
            return [];
        }

        // Handle List type
        if (strpos($decodedValue, 'List(') === 0) {
            // Extract content between List( and last )
            if (preg_match('/^List\((.*)\)$/', $decodedValue, $matches)) {
                return self::parseVoyagerList($matches[1]);
            }

            return [];
        }

        // Handle nested object (starts with parenthesis)
        if (strpos($decodedValue, '(') === 0) {
            // Check if it's a complex structure by looking for key-value pairs
            if (preg_match('/^\((.*)\)$/', $decodedValue, $matches)) {
                $innerContent = $matches[1];
                // If it contains a colon not within nested parentheses, it's a complex structure
                $colonPos = strpos($innerContent, ':');
                if ($colonPos !== false) {
                    // Count parentheses before the colon to ensure it's not within nested parentheses
                    $beforeColon = substr($innerContent, 0, $colonPos);
                    $openCount = substr_count($beforeColon, '(');
                    $closeCount = substr_count($beforeColon, ')');

                    if ($openCount === $closeCount) {
                        return self::parseVoyagerString($decodedValue);
                    }
                }
            }

            // If we reach here, it's a simple value with parentheses that should be preserved
            return $decodedValue;
        }

        // Handle boolean values
        if ($decodedValue === 'true') {
            return true;
        }
        if ($decodedValue === 'false') {
            return false;
        }

        // Return decoded value (or original if no decoding was needed)
        return $decodedValue;
    }

    private static function parseVoyagerList($content)
    {
        if (empty($content)) {
            return [];
        }

        $items = self::splitVoyagerPairs($content);
        $result = [];

        foreach ($items as $item) {
            // If item starts with (, it's either a nested structure or a value with parentheses
            if (strpos($item, '(') === 0) {
                $parsed = self::parseVoyagerValue($item);
                $result[] = $parsed;
            } else {
                $result[] = self::parseVoyagerValue($item);
            }
        }

        return $result;
    }
}
