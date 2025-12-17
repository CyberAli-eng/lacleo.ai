<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SocialResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CompanyController extends Controller
{
    public function social(Request $request)
    {
        $id = (string) $request->query('id', '');
        $domain = (string) $request->query('domain', '');
        $company = null;
        if ($id !== '') {
            try {
                $company = Company::findInElastic($id);
            } catch (\Throwable $e) {
                $company = null;
            }
        }
        if (! $company && $domain !== '') {
            $company = ['website' => $domain];
        }
        if (! $company) {
            $resp = response()->json([
                'social' => [],
                'cached' => false,
                'checked_at' => now()->toIso8601String(),
            ]);
            $resp->headers->set('X-Cache-Hit', 'false');

            return $resp;
        }

        $service = app(SocialResolverService::class);
        $result = $service->resolveForCompany($company);
        $social = array_values($result['social'] ?? []);
        usort($social, fn ($a, $b) => ($b['confidence'] <=> $a['confidence']) ?: strcmp($a['network'], $b['network']));
        $resp = response()->json([
            'social' => $social,
            'cached' => (bool) ($result['cached'] ?? false),
            'checked_at' => (string) ($result['checked_at'] ?? now()->toIso8601String()),
        ]);
        $resp->headers->set('X-Cache-Hit', ($result['cached'] ?? false) ? 'true' : 'false');

        return $resp;
    }

    public function refreshSocial(Request $request, string $id)
    {
        $company = null;
        try {
            $company = Company::findInElastic($id);
        } catch (\Throwable $e) {
            $company = null;
        }
        $domainParam = (string) $request->query('domain', '');
        if (! $company && $domainParam !== '') {
            $cacheKey = 'company_social_links:'.sha1(strtolower(trim($domainParam)));
            Cache::forget($cacheKey);
            $service = app(SocialResolverService::class);
            $result = $service->resolveForCompany(['website' => $domainParam]);
            $resp = response()->json($result);
            $resp->headers->set('X-Cache-Hit', 'false');

            return $resp;
        }
        if (! $company) {
            return response()->json(['error' => 'COMPANY_NOT_FOUND'], 404);
        }
        $norm = \App\Services\RecordNormalizer::normalizeCompany(is_array($company) ? $company : $company->toArray());
        $domain = strtolower(trim((string) ($norm['website'] ?? $norm['domain'] ?? '')));
        $cacheKey = 'company_social_links:'.sha1($domain);
        Cache::forget($cacheKey);
        $service = app(SocialResolverService::class);
        $result = $service->resolveForCompany($company);
        $resp = response()->json($result);
        $resp->headers->set('X-Cache-Hit', 'false');

        app(\App\Services\AuditLogger::class)->log('admin', (int) $request->user()->id, 'refresh_social', [
            'target_id' => is_array($company) ? ($company['id'] ?? null) : ($company?->id ?? null),
            'workspace_id' => null,
            'domain' => $domain,
        ]);

        return $resp;
    }
}
