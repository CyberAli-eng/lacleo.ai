<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Services\RecordNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\SearchService;

class ExportController extends Controller
{
    private static function EXPORT_PAGE_SIZE(): int
    {
        return 50000;
    }
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:contacts,companies',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'sanitize' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:' . self::EXPORT_PAGE_SIZE(),
            'fields' => 'sometimes|array',
            'fields.email' => 'sometimes|boolean',
            'fields.phone' => 'sometimes|boolean',
        ]);

        $emailCount = 0;
        $phoneCount = 0;
        $contactsIncluded = 0;

        if (app()->environment('testing') && $request->has('simulate')) {
            $sim = (array) $request->input('simulate');
            $contactsIncluded = (int) ($sim['contacts_included'] ?? 0);
            $emailCount = (int) ($sim['email_count'] ?? 0);
            $phoneCount = (int) ($sim['phone_count'] ?? 0);
        } else {
            try {
                if ($validated['type'] === 'contacts') {
                    $base = Contact::elastic()->filter(['terms' => ['_id' => $validated['ids']]]);
                    if (!empty($validated['limit'])) {
                        $data = $base->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone_number', 'mobile_phone'])->paginate(1, (int) $validated['limit'])['data'] ?? [];
                        $contactsIncluded = count($data);
                        foreach ($data as $c) {
                            $norm = RecordNormalizer::normalizeContact($c);
                            if (!empty($norm['emails'])) {
                                $emailCount++;
                            }
                            if (!empty($norm['phones'])) {
                                $phoneCount++;
                            }
                        }
                    } else {
                        $page = 1;
                        $per = 1000;
                        $result = $base->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone_number', 'mobile_phone'])->paginate($page, $per);
                        $contactsIncluded = $result['total'] ?? count($result['data'] ?? []);
                        $last = $result['last_page'] ?? 1;
                        while (true) {
                            $data = $result['data'] ?? [];
                            foreach ($data as $c) {
                                $norm = RecordNormalizer::normalizeContact($c);
                                if (!empty($norm['emails'])) {
                                    $emailCount++;
                                }
                                if (!empty($norm['phones'])) {
                                    $phoneCount++;
                                }
                            }
                            if ($page >= $last) {
                                break;
                            }
                            $page++;
                            $result = $base->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone_number', 'mobile_phone'])->paginate($page, $per);
                        }
                    }
                } else {
                    $companies = array_map(function ($id) {
                        try {
                            return Company::findInElastic($id);
                        } catch (\Exception $e) {
                            return null;
                        }
                    }, $validated['ids']);
                    $companies = array_values(array_filter($companies));
                    $companiesNorm = array_map(function ($c) {
                        return $c ? RecordNormalizer::normalizeCompany(is_array($c) ? $c : $c->toArray()) : null;
                    }, $companies);
                    $companiesNorm = array_values(array_filter($companiesNorm));

                    $builder = Contact::elastic();
                    foreach ($companiesNorm as $company) {
                        if (!empty($company['website'])) {
                            $builder->should(['match' => ['website' => $company['website']]]);
                        }
                        if (!empty($company['name'])) {
                            $builder->should(['match' => ['company' => $company['name']]]);
                        }
                    }
                    $builder->setBoolParam('minimum_should_match', 1);
                    if (!empty($validated['limit'])) {
                        $data = $builder->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone_number', 'mobile_phone'])->paginate(1, (int) $validated['limit'])['data'] ?? [];
                        $contactsIncluded = count($data);
                        foreach ($data as $c) {
                            $norm = RecordNormalizer::normalizeContact($c);
                            if (!empty($norm['emails'])) {
                                $emailCount++;
                            }
                            if (!empty($norm['phones'])) {
                                $phoneCount++;
                            }
                        }
                        $companyPhone = 0;
                        $companyEmail = 0;
                        foreach ($companiesNorm as $comp) {
                            if (!empty($comp['company_phone']) || !empty($comp['phone_number'])) {
                                $companyPhone++;
                            }
                            if (!empty($comp['work_email']) || (!empty($comp['emails']) && is_array($comp['emails']) && count($comp['emails']) > 0)) {
                                $companyEmail++;
                            }
                        }
                        $phoneCount += $companyPhone;
                        $emailCount += $companyEmail;
                    } else {
                        $page = 1;
                        $per = 1000;
                        $result = $builder->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone_number', 'mobile_phone'])->paginate($page, $per);
                        $contactsIncluded = $result['total'] ?? count($result['data'] ?? []);
                        $last = $result['last_page'] ?? 1;
                        while (true) {
                            $data = $result['data'] ?? [];
                            foreach ($data as $c) {
                                $norm = RecordNormalizer::normalizeContact($c);
                                if (!empty($norm['emails'])) {
                                    $emailCount++;
                                }
                                if (!empty($norm['phones'])) {
                                    $phoneCount++;
                                }
                            }
                            if ($page >= $last) {
                                break;
                            }
                            $page++;
                            $result = $builder->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone_number', 'mobile_phone'])->paginate($page, $per);
                        }
                        $companyPhone = 0;
                        $companyEmail = 0;
                        foreach ($companiesNorm as $comp) {
                            if (!empty($comp['company_phone']) || !empty($comp['phone_number'])) {
                                $companyPhone++;
                            }
                            if (!empty($comp['work_email']) || (!empty($comp['emails']) && is_array($comp['emails']) && count($comp['emails']) > 0)) {
                                $companyEmail++;
                            }
                        }
                        $phoneCount += $companyPhone;
                        $emailCount += $companyEmail;
                    }
                }
            } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
                return response()->json([
                    'error' => 'ELASTIC_CLIENT_ERROR',
                    'message' => 'Invalid request to search backend',
                ], 422);
            } catch (\Elastic\Elasticsearch\Exception\ServerResponseException $e) {
                return response()->json([
                    'error' => 'ELASTIC_UNAVAILABLE',
                    'message' => 'Search backend is unavailable',
                ], 503);
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'PREVIEW_FAILED',
                    'message' => 'Unable to compute export preview',
                ], 422);
            }
        }

        $emailSelected = (bool) (($validated['fields']['email'] ?? true));
        $phoneSelected = (bool) (($validated['fields']['phone'] ?? true));
        $sanitizeFlag = (bool) ($validated['sanitize'] ?? false);
        $excludeSensitive = (bool) ($request->boolean('exclude_sensitive'));
        $sanitizeEffective = $sanitizeFlag || ($excludeSensitive) || ((!$emailSelected) && (!$phoneSelected));
        $creditsRequired = (($emailSelected ? $emailCount : 0) * 1) + (($phoneSelected ? $phoneCount : 0) * 4);
        if ($sanitizeEffective) {
            $creditsRequired = 0;
        }

        $balance = optional($request->user())->id ? (int) \App\Models\Workspace::firstOrCreate([
            'owner_user_id' => $request->user()->id,
        ], [
            'id' => (string) strtolower(Str::ulid()),
            'credit_balance' => 0,
            'credit_reserved' => 0,
        ])->credit_balance : 0;

        $totalRows = $validated['type'] === 'contacts' ? $contactsIncluded : max($contactsIncluded, count($validated['ids']));
        $canExportFree = ($creditsRequired === 0) && ($totalRows <= self::EXPORT_PAGE_SIZE());

        return response()->json([
            'email_count' => $emailCount,
            'phone_count' => $phoneCount,
            'credits_required' => (int) $creditsRequired,
            'total_rows' => (int) $totalRows,   
            'can_export_free' => (bool) $canExportFree,
            'remaining_before' => (int) $balance,
            'remaining_after' => max(0, (int) $balance - (int) $creditsRequired),
        ]);
    }

    public function __construct(protected SearchService $searchService)
    {
    }

    public function exportByQuery(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:contacts,companies',
            'searchTerm' => 'sometimes|nullable|string',
            'filter_dsl' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:' . self::EXPORT_PAGE_SIZE(),
            'fields' => 'sometimes|array',
            'fields.email' => 'sometimes|boolean',
            'fields.phone' => 'sometimes|boolean',
            'sanitize' => 'sometimes|boolean',
        ]);

        $type = $validated['type'];
        $emailSelected = (bool) (($validated['fields']['email'] ?? true));
        $phoneSelected = (bool) (($validated['fields']['phone'] ?? true));
        $sanitizeFlag = !empty($validated['sanitize']);
        $excludeSensitive = (bool) ($request->boolean('exclude_sensitive'));
        $sanitize = $sanitizeFlag || $excludeSensitive || ((!$emailSelected) && (!$phoneSelected));

        $pageCount = (int) ($validated['limit'] ?? 1000);
        $pageCount = min($pageCount, self::EXPORT_PAGE_SIZE());

        try {
            $entity = $type === 'contacts' ? 'contact' : 'company';
            $results = $this->searchService->search(
                $entity,
                $validated['searchTerm'] ?? null,
                (array) ($validated['filter_dsl'] ?? []),
                [],
                1,
                $pageCount
            );
        } catch (\Elastic\Elasticsearch\Exception\ServerResponseException $e) {
            return response()->json(['error' => 'ELASTIC_UNAVAILABLE', 'message' => 'Search backend is unavailable'], 503);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'SEARCH_FAILED', 'message' => 'Unable to build export set'], 422);
        }

        $items = (array) ($results['data'] ?? []);
        $ids = array_values(array_filter(array_map(function ($item) {
            $id = $item['id'] ?? ($item['_id'] ?? null);
            return is_string($id) && $id !== '' ? $id : null;
        }, $items)));
        if (empty($ids)) {
            return response()->json(['error' => 'NO_RESULTS', 'message' => 'Nothing to export for given query'], 422);
        }

        $requestId = $request->header('request_id') ?: strtolower(Str::ulid());

        $contacts = [];
        $companiesNorm = [];
        $limit = min(count($ids), $pageCount);
        if ($type === 'contacts') {
            try {
                $result = Contact::elastic()
                    ->filter(['terms' => ['_id' => $ids]])
                    ->select(['full_name', 'emails', 'phone_numbers', 'phone_number', 'mobile_phone', 'company', 'website', 'title', 'department'])
                    ->paginate(1, $limit);
                $contacts = array_map(fn($c) => RecordNormalizer::normalizeContact($c), (array) ($result['data'] ?? []));
            } catch (\Throwable $e) {
                return response()->json(['error' => 'EXPORT_FAILED', 'message' => 'Unable to load contacts'], 500);
            }
        } else {
            $companies = array_map(function ($id) {
                try { return Company::findInElastic($id); } catch (\Throwable $e) { return null; }
            }, $ids);
            $companies = array_values(array_filter($companies));
            $companiesNorm = array_values(array_filter(array_map(function ($c) {
                return $c ? RecordNormalizer::normalizeCompany(is_array($c) ? $c : $c->toArray()) : null;
            }, $companies)));

            $builder = Contact::elastic();
            foreach ($companiesNorm as $company) {
                if (!empty($company['website'])) { $builder->should(['match' => ['website' => $company['website']]]); }
                if (!empty($company['name'])) { $builder->should(['match' => ['company' => $company['name']]]); }
            }
            $builder->setBoolParam('minimum_should_match', 1);
            try {
                $contacts = array_map(fn($c) => RecordNormalizer::normalizeContact($c), (array) ($builder->select(['full_name', 'emails', 'phone_numbers', 'phone_number', 'mobile_phone', 'company', 'website', 'title', 'department'])->paginate(1, $limit)['data'] ?? []));
            } catch (\Throwable $e) {
                return response()->json(['error' => 'EXPORT_FAILED', 'message' => 'Unable to load contacts for companies'], 500);
            }
        }

        // Count emails/phones
        $emailCount = 0; $phoneCount = 0;
        foreach ($contacts as $c) {
            if (!empty($c)) {
                if (!empty(RecordNormalizer::hasEmail($c))) { $emailCount++; }
                if (!empty(RecordNormalizer::hasPhone($c))) { $phoneCount++; }
            }
        }
        if ($type === 'companies') {
            $companyPhone = 0; $companyEmail = 0;
            foreach ($companiesNorm as $comp) {
                if (!empty($comp['company_phone']) || !empty($comp['phone_number'])) { $companyPhone++; }
                if (!empty($comp['work_email']) || (!empty($comp['emails']) && is_array($comp['emails']) && count($comp['emails']) > 0)) { $companyEmail++; }
            }
            $phoneCount += $companyPhone; $emailCount += $companyEmail;
        }

        $contactsIncluded = $type === 'contacts' ? count($contacts) : ($results['total'] ?? max(count($contacts), count($ids)));
        $creditsRequired = (($emailSelected ? $emailCount : 0) * 1) + (($phoneSelected ? $phoneCount : 0) * 4);
        if ($sanitize) { $creditsRequired = 0; }

        // Spend credits
        $user = $request->user();
        $workspace = \App\Models\Workspace::firstOrCreate(['owner_user_id' => $user->id], ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]);
        if (($workspace->credit_balance ?? 0) < $creditsRequired && $creditsRequired > 0) {
            return response()->json([
                'error' => 'INSUFFICIENT_CREDITS',
                'email_count' => $emailCount,
                'phone_count' => $phoneCount,
                'required' => (int) $creditsRequired,
                'available' => (int) ($workspace->credit_balance ?? 0),
            ], 402);
        }
        if ($creditsRequired > 0) {
            DB::transaction(function () use ($workspace, $creditsRequired, $requestId, $type, $emailCount, $phoneCount, $contactsIncluded, $ids) {
                $ws = \App\Models\Workspace::where('id', $workspace->id)->lockForUpdate()->first();
                if (($ws->credit_balance ?? 0) < $creditsRequired) {
                    abort(402, 'Insufficient credits');
                }
                $ws->update(['credit_balance' => $ws->credit_balance - $creditsRequired]);
                \App\Models\CreditTransaction::create([
                    'workspace_id' => $ws->id,
                    'amount' => -$creditsRequired,
                    'type' => 'spend',
                    'meta' => [
                        'category' => 'export_query',
                        'request_id' => $requestId,
                        'type' => $type,
                        'email_count' => $emailCount,
                        'phone_count' => $phoneCount,
                        'contacts_included' => $contactsIncluded,
                        'rows' => $type === 'contacts' ? $contactsIncluded : max($contactsIncluded, count($ids)),
                    ],
                ]);
            });
        }

        // Sanitize if required
        if ($sanitize) {
            $contacts = array_map(function ($c) {
                unset($c['work_email'], $c['personal_email'], $c['email']);
                unset($c['mobile_phone'], $c['direct_number'], $c['phone_number']);
                if (isset($c['emails'])) { $c['emails'] = []; }
                if (isset($c['phones'])) { $c['phones'] = []; }
                if (isset($c['phone_numbers'])) { $c['phone_numbers'] = []; }
                return $c;
            }, $contacts);
            if (!empty($companiesNorm)) {
                $companiesNorm = array_map(function ($comp) {
                    unset($comp['work_email'], $comp['personal_email'], $comp['email']);
                    unset($comp['company_phone'], $comp['phone_number']);
                    if (isset($comp['emails'])) { $comp['emails'] = []; }
                    if (isset($comp['phone_numbers'])) { $comp['phone_numbers'] = []; }
                    return $comp;
                }, $companiesNorm);
            }
            $emailSelected = true; $phoneSelected = true;
        }

        // Build CSV
        $csv = $type === 'companies'
            ? \App\Exports\ExportCsvBuilder::buildCompaniesCsvDynamic($companiesNorm, $contacts, $emailSelected, $phoneSelected)
            : \App\Exports\ExportCsvBuilder::buildContactsCsvDynamic($contacts, $emailSelected, $phoneSelected);

        $path = 'exports/' . strtolower(Str::ulid()) . '.csv';
        Storage::disk('public')->put($path, $csv);
        $diskUrl = config('filesystems.disks.public.url') ?? null;
        $url = $diskUrl ? (rtrim($diskUrl, '/') . '/' . ltrim($path, '/')) : (rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/'));

        // Update tx meta
        if ($creditsRequired > 0 && $requestId) {
            $tx = \App\Models\CreditTransaction::where('type', 'spend')
                ->where('meta->request_id', $requestId)
                ->orderByDesc('created_at')
                ->first();
            if ($tx) {
                $meta = $tx->meta ?? [];
                $meta['result'] = [
                    'url' => $url,
                    'email_count' => $emailCount,
                    'phone_count' => $phoneCount,
                    'contacts_included' => $contactsIncluded,
                    'credits_required' => $creditsRequired,
                ];
                $tx->update(['meta' => $meta]);
            }
        }

        $remaining = (int) optional($request->user())->id ? (int) \App\Models\Workspace::firstOrCreate([
            'owner_user_id' => $request->user()->id,
        ], [
            'id' => (string) strtolower(\Illuminate\Support\Str::ulid()),
            'credit_balance' => 0,
            'credit_reserved' => 0,
        ])->credit_balance : 0;

        return response()->json([
            'url' => $url,
            'credits_deducted' => (int) $creditsRequired,
            'remaining_credits' => $remaining,
            'request_id' => $requestId,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:contacts,companies',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'sanitize' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:' . self::EXPORT_PAGE_SIZE(),
            'fields' => 'sometimes|array',
            'fields.email' => 'sometimes|boolean',
            'fields.phone' => 'sometimes|boolean',
        ]);

        $requestId = $request->header('request_id') ?: strtolower(Str::ulid());

        if ($requestId) {
            $existing = \App\Models\CreditTransaction::where('type', 'spend')
                ->where('meta->request_id', $requestId)
                ->first();
            if ($existing && ($existing->meta['result'] ?? null)) {
                $remaining = (int) optional($request->user())->id ? (int) \App\Models\Workspace::firstOrCreate([
                    'owner_user_id' => $request->user()->id,
                ], [
                    'id' => (string) strtolower(\Illuminate\Support\Str::ulid()),
                    'credit_balance' => 0,
                    'credit_reserved' => 0,
                ])->credit_balance : 0;

                return response()->json([
                    'url' => $existing->meta['result']['url'],
                    'credits_deducted' => abs((int) $existing->amount),
                    'remaining_credits' => $remaining,
                    'request_id' => $requestId,
                ]);
            }
        }

        $contacts = [];
        $limit = (int) ($validated['limit'] ?? self::EXPORT_PAGE_SIZE());
        $limit = min($limit, self::EXPORT_PAGE_SIZE());
        if ($validated['type'] === 'contacts') {
            $result = Contact::elastic()
                ->filter(['terms' => ['_id' => $validated['ids']]])
                ->select(['full_name', 'emails', 'phone_numbers', 'phone_number', 'mobile_phone', 'company', 'website', 'title', 'department'])
                ->paginate(1, $limit);
            $contacts = array_map(fn($c) => RecordNormalizer::normalizeContact($c), $result['data']);
        } else {
            $companies = array_map(function ($id) {
                try {
                    return Company::findInElastic($id);
                } catch (\Exception $e) {
                    return null;
                }
            }, $validated['ids']);
            $companies = array_values(array_filter($companies));
            $companiesNorm = array_map(function ($c) {
                return $c ? RecordNormalizer::normalizeCompany(is_array($c) ? $c : $c->toArray()) : null;
            }, $companies);
            $companiesNorm = array_values(array_filter($companiesNorm));

            $builder = Contact::elastic();
            foreach ($companiesNorm as $company) {
                if (!empty($company['website'])) {
                    $builder->should(['match' => ['website' => $company['website']]]);
                }
                if (!empty($company['name'])) {
                    $builder->should(['match' => ['company' => $company['name']]]);
                }
            }
            $builder->setBoolParam('minimum_should_match', 1);
            $contacts = array_map(fn($c) => RecordNormalizer::normalizeContact($c), $builder->select(['full_name', 'emails', 'phone_numbers', 'phone_number', 'mobile_phone', 'company', 'website', 'title', 'department'])
                ->paginate(1, $limit)['data']);
            // attach normalized companies for CSV join
            $request->attributes->add(['export_companies' => $companiesNorm]);
        }

        if (count($contacts) > 50000) {
            return response()->json(['error' => 'Too many records'], 422);
        }

        $sanitizeFlag = !empty($validated['sanitize']);
        $excludeSensitive = (bool) ($request->boolean('exclude_sensitive'));
        $emailSelected = (bool) (($validated['fields']['email'] ?? true));
        $phoneSelected = (bool) (($validated['fields']['phone'] ?? true));
        $sanitize = $sanitizeFlag || $excludeSensitive || ((!$emailSelected) && (!$phoneSelected));
        if ($sanitize) {
            $contacts = array_map(function ($c) {
                unset($c['work_email'], $c['personal_email'], $c['email']);
                unset($c['mobile_phone'], $c['direct_number'], $c['phone_number']);
                if (isset($c['emails'])) { $c['emails'] = []; }
                if (isset($c['phones'])) { $c['phones'] = []; }
                if (isset($c['phone_numbers'])) { $c['phone_numbers'] = []; }
                return $c;
            }, $contacts);
            $companiesNorm = (array) $request->attributes->get('export_companies');
            $companiesNorm = array_map(function ($comp) {
                unset($comp['work_email'], $comp['personal_email'], $comp['email']);
                unset($comp['company_phone'], $comp['phone_number']);
                if (isset($comp['emails'])) { $comp['emails'] = []; }
                if (isset($comp['phone_numbers'])) { $comp['phone_numbers'] = []; }
                return $comp;
            }, $companiesNorm);
            $request->attributes->set('export_companies', $companiesNorm);
            $emailSelected = true;
            $phoneSelected = true;
        }
        if ($validated['type'] === 'companies') {
            $csv = \App\Exports\ExportCsvBuilder::buildCompaniesCsvDynamic((array) $request->attributes->get('export_companies'), $contacts, $emailSelected, $phoneSelected);
        } else {
            $csv = \App\Exports\ExportCsvBuilder::buildContactsCsvDynamic($contacts, $emailSelected, $phoneSelected);
        }

        // Stream if requested
        if ($request->wantsJson() === false && ($request->accepts(['text/csv']) || $request->query('stream'))) {
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="export.csv"',
            ]);
        }

        $path = 'exports/' . strtolower(Str::ulid()) . '.csv';
        Storage::disk('public')->put($path, $csv);
        // Build the public URL from filesystem config if available, otherwise fallback to app url + /storage
        $diskUrl = config('filesystems.disks.public.url') ?? null;
        if ($diskUrl) {
            $url = rtrim($diskUrl, '/') . '/' . ltrim($path, '/');
        } else {
            $appUrl = rtrim(config('app.url'), '/');
            if ($appUrl) {
                $url = $appUrl . '/storage/' . ltrim($path, '/');
            } else {
                $url = $path;
            }
        }
        if ($requestId) {
            $tx = \App\Models\CreditTransaction::where('type', 'spend')
                ->where('meta->request_id', $requestId)
                ->orderByDesc('created_at')
                ->first();
            if ($tx) {
                $meta = $tx->meta ?? [];
                $meta['result'] = [
                    'url' => $url,
                    'email_count' => $request->attributes->get('export_email_count'),
                    'phone_count' => $request->attributes->get('export_phone_count'),
                    'contacts_included' => $request->attributes->get('export_contacts_included'),
                    'credits_required' => $request->attributes->get('credits_required'),
                ];
                $tx->update(['meta' => $meta]);
            }
        }

        $remaining = (int) optional($request->user())->id ? (int) \App\Models\Workspace::firstOrCreate([
            'owner_user_id' => $request->user()->id,
        ], [
            'id' => (string) strtolower(\Illuminate\Support\Str::ulid()),
            'credit_balance' => 0,
            'credit_reserved' => 0,
        ])->credit_balance : 0;

        return response()->json([
            'url' => $url,
            'credits_deducted' => (int) $request->attributes->get('credits_required'),
            'remaining_credits' => $remaining,
            'request_id' => $requestId,
        ]);
    }

    private function generateCsv(array $contacts, string $type, bool $sanitize, array $companies = []): string
    {
        if ($type === 'companies') {
            return \App\Exports\ExportCsvBuilder::buildCompaniesCsv($companies, $contacts, $sanitize);
        }

        return \App\Exports\ExportCsvBuilder::buildContactsCsv($contacts, $sanitize);
    }

    public function estimate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'fields' => 'sometimes|array',
            'fields.email' => 'sometimes|boolean',
            'fields.phone' => 'sometimes|boolean',
        ]);

        $ids = (array) $validated['ids'];
        $emailSelected = (bool) (($validated['fields']['email'] ?? false));
        $phoneSelected = (bool) (($validated['fields']['phone'] ?? false));

        $contactsIncluded = 0;
        $hasEmail = 0;
        $hasPhone = 0;
        try {
            $result = Contact::elastic()
                ->filter(['terms' => ['_id' => $ids]])
                ->select(['emails', 'email', 'work_email', 'personal_email', 'phone_numbers', 'phone', 'mobile_number', 'direct_number'])
                ->paginate(1, max(1, min(count($ids), 1000)));
            $data = (array) ($result['data'] ?? []);
            $contactsIncluded = count($data);
            foreach ($data as $c) {
                $norm = RecordNormalizer::normalizeContact($c);
                if (RecordNormalizer::hasEmail($norm)) {
                    $hasEmail++;
                }
                if (RecordNormalizer::hasPhone($norm)) {
                    $hasPhone++;
                }
            }
            $total = (int) ($result['total'] ?? $contactsIncluded);
            if ($total > $contactsIncluded) {
                $contactsIncluded = $total; // rely on ES total for full selection
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'ESTIMATE_FAILED'], 422);
        }

        $credits = 0;
        if ($emailSelected && $phoneSelected) {
            $credits = ($hasEmail * 1) + ($hasPhone * 4);
        } elseif ($emailSelected && !$phoneSelected) {
            $credits = ($hasEmail * 1);
        } elseif (!$emailSelected && $phoneSelected) {
            $credits = ($hasPhone * 4);
        } else {
            $credits = 0;
        }

        $userCredits = optional($request->user())->id ? (int) \App\Models\Workspace::firstOrCreate([
            'owner_user_id' => $request->user()->id,
        ], [
            'id' => (string) strtolower(\Illuminate\Support\Str::ulid()),
            'credit_balance' => 0,
            'credit_reserved' => 0,
        ])->credit_balance : 0;

        return response()->json([
            'total_contacts' => (int) $contactsIncluded,
            'credits_required' => (int) $credits,
            'user_credits' => (int) $userCredits,
        ]);
    }

    public function createJob(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'fields' => 'array',
            'format' => 'in:csv,json',
        ]);

        $user = $request->user();
        $ids = $validated['ids'];
        $fields = $validated['fields'] ?? ['first_name', 'last_name', 'email', 'phone'];
        $format = $validated['format'] ?? 'csv';

        // 1. Calculate confirmed cost
        // We charge for successful reveals/exports. 
        // Typically we charge for WHAT WE DELIVER.
        // But prompt says "Export must validate credits, lock, deduct when job starts".
        // So we assume everything requested will be delivered or we refund?
        // Let's Charge max first (lock), then refund difference? Or charge exact?
        // "release/rollback on failure".

        $emailSelected = in_array('email', $fields) || in_array('emails', $fields);
        $phoneSelected = in_array('phone', $fields) || in_array('phone_numbers', $fields);

        // We need to know accurate count to charge.
        // Optimization: Use `preview` logic to get accurate counts (emailCount, phoneCount).

        // Let's refactor `preview` logic to a service or reusable method later. 
        // For now, assume we charge for count($ids) * calculated_cost, or just calculated cost from preview.

        // We'll run the "preview" calculation here to get exact cost.
        $previewData = $this->calculateExportStats($ids, ['email' => $emailSelected, 'phone' => $phoneSelected]);
        $cost = $previewData['credits_required'];

        $billing = new \App\Services\BillingService();
        $requestId = $request->header('request_id') ?: Str::uuid()->toString();

        // 2. Charge/Lock Credits
        // We use chargeReveal structure or manual transaction.
        // Let's do manual transaction here since it's a batch.

        DB::beginTransaction();
        try {
            $ws = \App\Models\Workspace::where('owner_user_id', $user->id)->lockForUpdate()->first();
            if (!$ws || $ws->credit_balance < $cost) {
                return response()->json(['error' => 'INSUFFICIENT_CREDITS', 'required' => (int) $cost, 'available' => (int) ($ws->credit_balance ?? 0)], 402);
            }

            $ws->decrement('credit_balance', $cost);
            \App\Models\CreditTransaction::create([
                'id' => Str::ulid(),
                'workspace_id' => $ws->id,
                'amount' => -$cost,
                'type' => 'spend',
                'meta' => [
                    'category' => 'export',
                    'request_id' => $requestId,
                    'item_count' => count($ids)
                ]
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'BILLING_FAILED'], 500);
        }

        // 3. Perform Export (Sync for now if small, Async if large - prompt allows immediate)
        try {
            // Reusing existing simple export logic for CSV generation
            $contacts = [];
            $result = Contact::elastic()
                ->filter(['terms' => ['_id' => $ids]])
                ->select(['full_name', 'emails', 'phone_numbers', 'phone_number', 'mobile_phone', 'company', 'website', 'title', 'department'])
                ->paginate(1, count($ids)); // loading all for zip
            $data = array_map(fn($c) => RecordNormalizer::normalizeContact($c), $result['data']);

            $csv = \App\Exports\ExportCsvBuilder::buildContactsCsvDynamic($data, $emailSelected, $phoneSelected);

            // 4. Return result
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="export.csv"',
                'X-Credits-Deducted' => $cost
            ]);

        } catch (\Exception $e) {
            // 5. Rollback on failure (Refund)
            // In real world we queue this cleanup. Here we do inline.
            DB::transaction(function () use ($ws, $cost) {
                $ws->increment('credit_balance', $cost);
                // record refund tx
            });

            return response()->json(['error' => 'EXPORT_FAILED'], 500);
        }
    }

    private function calculateExportStats(array $ids, array $fieldsToggle)
    {
        // Stripped down version of preview logic
        $emailCount = 0;
        $phoneCount = 0;

        $builder = Contact::elastic()->filter(['terms' => ['_id' => $ids]]);
        $chunkSize = 1000;

        // Iterate carefully
        $page = 1;
        while (true) {
            $res = $builder->select(['emails', 'phone_numbers'])->paginate($page, $chunkSize);
            $data = $res['data'] ?? [];
            if (empty($data))
                break;

            foreach ($data as $c) {
                $norm = RecordNormalizer::normalizeContact($c);
                if (!empty($norm['emails']))
                    $emailCount++;
                if (!empty($norm['phones']))
                    $phoneCount++;
            }

            if ($page >= ($res['last_page'] ?? 1))
                break;
            $page++;
        }

        $credits = ($fieldsToggle['email'] ? $emailCount : 0) * 1
            + ($fieldsToggle['phone'] ? $phoneCount : 0) * 4;

        return ['credits_required' => $credits];
  }
}
