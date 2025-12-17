<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LogoService
{
    public function getLogo(string $domain): ?string
    {
        $key = 'company_logo:'.strtolower($domain);

        return Cache::remember($key, now()->addDays(7), function () use ($domain) {
            $sources = [
                "https://logo.clearbit.com/{$domain}",
                "https://www.google.com/s2/favicons?sz=256&domain_url={$domain}",
                "https://icons.duckduckgo.com/ip3/{$domain}.ico",
            ];

            foreach ($sources as $url) {
                try {
                    $resp = Http::timeout(5)->head($url);
                    if ($resp->ok()) {
                        return $url;
                    }
                } catch (\Throwable $e) {
                    // try next
                }
            }

            return null;
        });
    }
}
