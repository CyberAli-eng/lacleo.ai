<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SocialResolverService
{
    public function resolveForCompany($company): array
    {
        $norm = is_array($company) ? RecordNormalizer::normalizeCompany($company) : (is_object($company) ? RecordNormalizer::normalizeCompany($company->toArray()) : []);
        $domain = strtolower(trim((string) ($norm['website'] ?? $norm['domain'] ?? '')));
        $name = (string) ($norm['name'] ?? $norm['company'] ?? '');

        $cacheTtl = (int) env('SOCIAL_CACHE_TTL_SECONDS', 86400);
        $cacheKey = 'company_social_links:'.sha1($domain);
        $cachedPayload = Cache::get($cacheKey);
        if ($cachedPayload) {
            return [
                'social' => $cachedPayload['social'] ?? [],
                'cached' => true,
                'checked_at' => $cachedPayload['checked_at'] ?? now()->toIso8601String(),
            ];
        }

        $allowFetch = (bool) env('SOCIAL_RESOLVER_ALLOW_FETCH', false);
        $timeout = (int) env('SOCIAL_RESOLVER_FETCH_TIMEOUT_SECONDS', 3);

        $items = [];

        $add = function (string $network, string $url, string $source) use (&$items, $domain, $name) {
            $normalized = self::normalizeUrl($url);
            $confidence = 0.0;
            $host = parse_url($normalized, PHP_URL_HOST) ?: '';
            $path = parse_url($normalized, PHP_URL_PATH) ?: '';
            $slug = self::slugify(($name ?: ''));

            if ($network === 'homepage' && $domain !== '' && stripos($host, $domain) !== false) {
                $confidence += 0.6;
            }

            $patterns = [
                'linkedin' => '#linkedin\.com\/(company|in)\/[^\/]+#i',
                'twitter' => '#(x\.com|twitter\.com)\/[^\/]+#i',
                'facebook' => '#facebook\.com\/[^\/]+#i',
                'instagram' => '#instagram\.com\/[^\/]+#i',
                'github' => '#github\.com\/[^\/]+#i',
                'crunchbase' => '#crunchbase\.com\/(organization|company)\/[^\/]+#i',
                'youtube' => '#youtube\.com\/(c|channel|@)[^\/]+#i',
                'tiktok' => '#tiktok\.com\/@[^\/]+#i',
            ];
            if (isset($patterns[$network]) && preg_match($patterns[$network], $host.$path)) {
                $confidence += 0.2;
            }

            if ($slug !== '' && $path !== '') {
                if (stripos($path, $slug) !== false) {
                    $confidence += 0.15;
                }
            }

            $heuristics = [
                'linkedin' => fn ($p) => preg_match('#\/(company|in)\/[^\/]+#i', $p),
                'twitter' => fn ($p) => preg_match('#\/[^\/]{2,}#', $p),
                'facebook' => fn ($p) => preg_match('#\/[^\/]{2,}#', $p),
                'instagram' => fn ($p) => preg_match('#\/[^\/]{2,}#', $p),
                'github' => fn ($p) => preg_match('#\/[^\/]{2,}#', $p),
                'crunchbase' => fn ($p) => preg_match('#\/(organization|company)\/[^\/]+#i', $p),
                'youtube' => fn ($p) => preg_match('#\/(c|channel|@)[^\/]+#i', $p),
                'tiktok' => fn ($p) => preg_match('#\/@[^\/]+#i', $p),
            ];
            if (isset($heuristics[$network]) && $heuristics[$network]($path)) {
                $confidence += 0.05;
            }

            $confidence = max(0.0, min(1.0, $confidence));
            if ($confidence < 0.25) {
                return;
            }

            $items[] = [
                'network' => $network,
                'url' => $url,
                'normalized_url' => $normalized,
                'confidence' => round($confidence, 2),
                'source' => $source,
            ];
        };

        $known = [
            'linkedin' => (string) ($norm['linkedin_url'] ?? ''),
            'facebook' => (string) ($norm['facebook_url'] ?? ''),
            'twitter' => (string) ($norm['twitter_url'] ?? ''),
        ];
        foreach ($known as $net => $url) {
            if ($url !== '') {
                $add($net, $url, 'normalized');
            }
        }

        if ($domain !== '') {
            $add('homepage', (str_starts_with($domain, 'http') ? $domain : 'https://'.$domain), 'domain');
        }

        $slug = self::slugify($name !== '' ? $name : ($domain !== '' ? explode('.', $domain)[0] : ''));
        if ($slug !== '') {
            $add('linkedin', 'https://www.linkedin.com/company/'.$slug, 'heuristic');
            $add('twitter', 'https://twitter.com/'.$slug, 'heuristic');
            $add('facebook', 'https://www.facebook.com/'.$slug, 'heuristic');
            $add('github', 'https://github.com/'.$slug, 'heuristic');
            $add('crunchbase', 'https://www.crunchbase.com/organization/'.$slug, 'heuristic');
            $add('youtube', 'https://www.youtube.com/@'.$slug, 'heuristic');
            $add('tiktok', 'https://www.tiktok.com/@'.$slug, 'heuristic');
            $add('instagram', 'https://www.instagram.com/'.$slug, 'heuristic');
        }

        if ($allowFetch) {
            $targets = collect($items)->take(3)->all();
            foreach ($targets as $t) {
                try {
                    Http::timeout($timeout)->head($t['normalized_url']);
                } catch (\Throwable $e) {
                }
            }
        }

        usort($items, fn ($a, $b) => ($b['confidence'] <=> $a['confidence']) ?: strcmp($a['network'], $b['network']));
        $payload = ['social' => $items, 'checked_at' => now()->toIso8601String()];
        Cache::put($cacheKey, $payload, now()->addSeconds($cacheTtl));

        return [
            'social' => $items,
            'cached' => false,
            'checked_at' => $payload['checked_at'],
        ];
    }

    public static function normalizeUrl(string $url): string
    {
        $u = trim($url);
        if ($u === '') {
            return '';
        }
        if (! str_starts_with($u, 'http')) {
            $u = 'https://'.$u;
        }
        $parts = parse_url($u);
        if (! $parts) {
            return $u;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
        if ($query !== '') {
            parse_str($query, $params);
            foreach (array_keys($params) as $k) {
                if (preg_match('/^(utm_|gclid|fbclid)/i', $k)) {
                    unset($params[$k]);
                }
            }
            $query = http_build_query($params);
        }
        $authority = $host;
        $normalized = $scheme.'://'.$authority.$path.($query ? '?'.$query : '');

        return $normalized;
    }

    private static function slugify(string $s): string
    {
        $t = strtolower(preg_replace('/[^a-z0-9]+/i', '', $s));

        return $t;
    }
}
