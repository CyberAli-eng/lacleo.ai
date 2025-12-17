<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class FilterSuggestController extends Controller
{
    public function companies(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $query = $this->buildSuggestQuery($q);
        try {
            $res = Company::searchInElastic($query);
        } catch (\Throwable $e) {
            return response()->json(['data' => []]);
        }

        $out = [];
        foreach (($res['hits']['hits'] ?? []) as $hit) {
            $src = $hit['_source'] ?? [];
            $out[] = [
                'id' => $hit['_id'] ?? null,
                'name' => $src['company'] ?? ($src['name'] ?? null),
                'domain' => $src['website'] ?? ($src['domain'] ?? null),
                'employee_count' => $src['number_of_employees'] ?? ($src['employee_count'] ?? ($src['employees'] ?? null)),
            ];
            if (count($out) >= 20) break;
        }

        return response()->json(['data' => $out]);
    }

    private function buildSuggestQuery(string $q): array
    {
        $clean = strtolower(trim($q));
        $clean = preg_replace(['#^https?://#', '#^www\.#'], '', $clean);

        $should = [];
        // Name prefix matching and aliases
        $should[] = [
            'multi_match' => [
                'query' => $clean,
                'type' => 'phrase_prefix',
                'fields' => ['company.prefix^3', 'company_also_known_as.prefix^2', 'company.joined'],
                'operator' => 'and',
                'prefix_length' => 1,
            ],
        ];
        // Exact keyword for full name
        $should[] = ['term' => ['company.keyword' => ['value' => $clean, 'boost' => 5]]];
        // Domain exact and wildcard
        $should[] = ['term' => ['website' => ['value' => $clean, 'boost' => 6]]];
        $should[] = ['wildcard' => ['website' => ['value' => "*$clean*", 'boost' => 3]]];

        $query = [
            'query' => [
                'bool' => [
                    'should' => $should,
                    'minimum_should_match' => 1,
                ],
            ],
            'size' => 20,
            'track_total_hits' => false,
            'sort' => [
                ['employee_count' => ['order' => 'desc']],
            ],
            '_source' => ['company', 'name', 'website', 'domain', 'number_of_employees', 'employee_count', 'employees'],
        ];

        return $query;
    }

    public function existence(Request $request)
    {
        $names = array_values(array_filter(array_map(function ($s) {
            return trim((string) $s);
        }, (array) $request->query('names', [])), function ($x) {
            return $x !== '';
        }));

        $domainsRaw = (array) $request->query('domains', []);
        $domains = array_values(array_filter(array_map(function ($d) {
            $clean = strtolower(trim((string) $d));
            $clean = preg_replace(['#^https?://#', '#^www\.#'], '', $clean);
            return $clean;
        }, $domainsRaw), function ($x) {
            return $x !== '';
        }));

        if (empty($names) && empty($domains)) {
            return response()->json(['data' => ['found_names' => [], 'found_domains' => []]]);
        }

        $should = [];
        if (!empty($domains)) {
            $should[] = ['terms' => ['domain' => $domains]];
            $should[] = ['terms' => ['website' => $domains]];
        }
        if (!empty($names)) {
            $should[] = ['terms' => ['company.keyword' => $names]];
        }

        $query = [
            'query' => [
                'bool' => [
                    'should' => $should,
                    'minimum_should_match' => 1,
                ],
            ],
            'size' => min(max(count($names) + count($domains), 1), 10000),
            'track_total_hits' => false,
            '_source' => ['company', 'name', 'website', 'domain'],
        ];

        try {
            $res = Company::searchInElastic($query);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['found_names' => [], 'found_domains' => []]]);
        }

        $foundNames = [];
        $foundDomains = [];
        $namesSet = array_flip($names);
        $domainsSet = array_flip($domains);
        foreach ((array) ($res['hits']['hits'] ?? []) as $hit) {
            $src = (array) ($hit['_source'] ?? []);
            $nm = trim((string) ($src['company'] ?? ($src['name'] ?? '')));
            if ($nm !== '' && isset($namesSet[$nm])) {
                $foundNames[] = $nm;
            }
            $dom = strtolower(trim((string) ($src['website'] ?? ($src['domain'] ?? ''))));
            if ($dom !== '' && isset($domainsSet[$dom])) {
                $foundDomains[] = $dom;
            }
            if (count($foundNames) >= 10000 && count($foundDomains) >= 10000) {
                break;
            }
        }

        return response()->json(['data' => [
            'found_names' => array_values(array_unique($foundNames)),
            'found_domains' => array_values(array_unique($foundDomains)),
        ]]);
    }

    public function existencePost(Request $request)
    {
        $names = array_values(array_filter(array_map(function ($s) {
            return trim((string) $s);
        }, (array) $request->input('names', [])), function ($x) {
            return $x !== '';
        }));

        $domainsRaw = (array) $request->input('domains', []);
        $domains = array_values(array_filter(array_map(function ($d) {
            $clean = strtolower(trim((string) $d));
            $clean = preg_replace(['#^https?://#', '#^www\.#'], '', $clean);
            return $clean;
        }, $domainsRaw), function ($x) {
            return $x !== '';
        }));

        if (empty($names) && empty($domains)) {
            return response()->json(['data' => ['found_names' => [], 'found_domains' => []]]);
        }

        $should = [];
        if (!empty($domains)) {
            $should[] = ['terms' => ['domain' => $domains]];
            $should[] = ['terms' => ['website' => $domains]];
        }
        if (!empty($names)) {
            $should[] = ['terms' => ['company.keyword' => $names]];
        }

        $query = [
            'query' => [
                'bool' => [
                    'should' => $should,
                    'minimum_should_match' => 1,
                ],
            ],
            'size' => min(max(count($names) + count($domains), 1), 10000),
            'track_total_hits' => false,
            '_source' => ['company', 'name', 'website', 'domain'],
        ];

        try {
            $res = Company::searchInElastic($query);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['found_names' => [], 'found_domains' => []]]);
        }

        $foundNames = [];
        $foundDomains = [];
        $namesSet = array_flip($names);
        $domainsSet = array_flip($domains);
        foreach ((array) ($res['hits']['hits'] ?? []) as $hit) {
            $src = (array) ($hit['_source'] ?? []);
            $nm = trim((string) ($src['company'] ?? ($src['name'] ?? '')));
            if ($nm !== '' && isset($namesSet[$nm])) {
                $foundNames[] = $nm;
            }
            $dom = strtolower(trim((string) ($src['website'] ?? ($src['domain'] ?? ''))));
            if ($dom !== '' && isset($domainsSet[$dom])) {
                $foundDomains[] = $dom;
            }
            if (count($foundNames) >= 10000 && count($foundDomains) >= 10000) {
                break;
            }
        }

        return response()->json(['data' => [
            'found_names' => array_values(array_unique($foundNames)),
            'found_domains' => array_values(array_unique($foundDomains)),
        ]]);
    }

    public function bulkApply(Request $request)
    {
        $type = (string) $request->input('type', 'name');
        $values = (array) $request->input('values', []);
        $searchContext = (string) $request->input('searchContext', 'contacts');

        $names = [];
        $domains = [];
        if ($type === 'domain') {
            $domainsRaw = $values;
            $domains = array_values(array_filter(array_map(function ($d) {
                $clean = strtolower(trim((string) $d));
                $clean = preg_replace(['#^https?://#', '#^www\.#'], '', $clean);
                return $clean;
            }, $domainsRaw), function ($x) {
                return $x !== '';
            }));
        } else {
            $names = array_values(array_filter(array_map(function ($s) {
                return trim((string) $s);
            }, $values), function ($x) {
                return $x !== '';
            }));
        }

        $should = [];
        if (!empty($domains)) {
            $should[] = ['terms' => ['domain' => $domains]];
            $should[] = ['terms' => ['website' => $domains]];
        }
        if (!empty($names)) {
            $should[] = ['terms' => ['company.keyword' => $names]];
        }

        if (empty($should)) {
            return response()->json(['data' => [
                'applied' => [],
                'skipped' => [],
                'type' => $type,
                'searchContext' => $searchContext,
            ]]);
        }

        $query = [
            'query' => [
                'bool' => [
                    'should' => $should,
                    'minimum_should_match' => 1,
                ],
            ],
            'size' => min(max(count($names) + count($domains), 1), 10000),
            'track_total_hits' => false,
            '_source' => ['company', 'name', 'website', 'domain'],
        ];

        try {
            $res = Company::searchInElastic($query);
        } catch (\Throwable $e) {
            $res = ['hits' => ['hits' => []]];
        }

        $foundSet = [];
        foreach ((array) ($res['hits']['hits'] ?? []) as $hit) {
            $src = (array) ($hit['_source'] ?? []);
            if ($type === 'domain') {
                $dom = strtolower(trim((string) ($src['website'] ?? ($src['domain'] ?? ''))));
                if ($dom !== '') {
                    $foundSet[$dom] = true;
                }
            } else {
                $nm = trim((string) ($src['company'] ?? ($src['name'] ?? '')));
                if ($nm !== '') {
                    $foundSet[$nm] = true;
                }
            }
        }

        $applied = [];
        $skipped = [];
        if ($type === 'domain') {
            foreach ($domains as $d) {
                if (isset($foundSet[$d])) {
                    $applied[] = $d;
                } else {
                    $skipped[] = $d;
                }
            }
        } else {
            foreach ($names as $n) {
                if (isset($foundSet[$n])) {
                    $applied[] = $n;
                } else {
                    $skipped[] = $n;
                }
            }
        }

        return response()->json(['data' => [
            'applied' => array_values(array_unique($applied)),
            'skipped' => array_values(array_unique($skipped)),
            'type' => $type,
            'searchContext' => $searchContext,
        ]]);
    }
}
