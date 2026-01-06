<?php

namespace App\Services;

use App\Elasticsearch\ElasticClient;
use App\Enums\EnrichmentStatus;
use App\Jobs\ProcessContactEnrichment;
use App\Models\Contact;
use App\Models\EnrichmentRequest;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactEnrichmentService
{
    protected string $primeroleBaseUrl;

    protected string $primeroleApiAuthToken;

    protected string $primeroleWorkspaceToken;

    public function __construct(protected ElasticClient $elasticClient)
    {
        $this->primeroleBaseUrl = config('services.primerole.base_url');
        $this->primeroleApiAuthToken = config('services.primerole.api_auth_token');
        $this->primeroleWorkspaceToken = config('services.primerole.workspace_token');
    }

    /**
     * Handle enrichment request
     */
    public function handleEnrichmentRequest(array $data): EnrichmentRequest
    {
        try {
            // Create enrichment request
            $enrichmentRequest = EnrichmentRequest::create([
                'transaction_id' => $data['transaction_id'] ?? null,
                'request_data' => $data,
                'status' => EnrichmentStatus::QUEUED,
                'last_processed_at' => null,
            ]);

            ProcessContactEnrichment::dispatch($enrichmentRequest);

            return $enrichmentRequest;
        } catch (Exception $e) {
            Log::error('Failed to handle enrichment request', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Create contact in PrimeRole
     */
    public function createPrimeRoleContact(array $requestData): array
    {

        $contactData = $requestData['contact'];
        $companyData = $requestData['company'] ?? null;

        $payload = [
            'records' => [
                array_filter([
                    'contact_key' => $requestData['transaction_id'] ?? null,
                    'email' => $contactData['email'] ?? null,
                    'first_name' => $contactData['first_name'] ?? null,
                    'last_name' => $contactData['last_name'] ?? null,
                    'full_name' => $contactData['full_name'] ?? null,
                    'linkedin_url' => $contactData['linkedin_url'] ?? null,
                    'company_name' => $companyData['name'] ?? null,
                    'domain' => $companyData['domain'] ?? null,
                ], function ($value) {
                    return $value !== null;
                }),
            ],
        ];


        try {
            $response = Http::withToken($this->primeroleApiAuthToken)
                ->timeout(30)
                ->connectTimeout(10)
                ->withHeader('X-Workspace-Id', $this->primeroleWorkspaceToken)
                ->post("{$this->primeroleBaseUrl}/contacts", $payload);

            if ($response->successful()) {

                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $errorMessage = 'Contact Erichnment: Failed to create contact' . $response->body();
            Log::error($errorMessage, [
                'status_code' => $response->status(),
                'transaction_id' => $requestData['transaction_id'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Contact Erichnment: Failed to create contact', [
                'error' => $e->getMessage(),
                'url' => "{$this->primeroleBaseUrl}/contacts",
                'transaction_id' => $requestData['transaction_id'] ?? null,
            ]);
            throw new Exception('Contact Erichnment: Failed to create contact' . $e->getMessage());
        }
    }

    /**
     * Start enrichment process
     */
    public function startEnrichment(string $contactId): array
    {

        $payload = [
            'records' => [
                [
                    'contact_id' => $contactId,
                ],
            ],
        ];

        if (config('app.debug')) {
            Log::debug('Enrichment payload', ['payload' => $payload]);
        }

        try {
            $response = Http::withToken($this->primeroleApiAuthToken)
                ->withHeader('X-Workspace-Id', $this->primeroleWorkspaceToken)
                ->post("{$this->primeroleBaseUrl}/enrich", $payload);

            if ($response->successful()) {

                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $errorMessage = 'Failed to start enrichment: ' . $response->body();
            Log::error($errorMessage, [
                'status_code' => $response->status(),
                'contact_id' => $contactId,
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Exception while starting enrichment', [
                'message' => $e->getMessage(),
                'contact_id' => $contactId,
                'payload' => $payload,
            ]);
            throw new Exception('Failed to start enrichment: ' . $e->getMessage());
        }
    }

    /**
     * Check enrichment status
     */
    public function checkEnrichmentStatus(string $contactId): array
    {

        try {
            $response = Http::withToken($this->primeroleApiAuthToken)
                ->withHeader('X-Workspace-Id', $this->primeroleWorkspaceToken)
                ->get("{$this->primeroleBaseUrl}/contacts/{$contactId}");

            if ($response->successful()) {

                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $errorMessage = 'Failed to check enrichment status: ' . $response->body();
            Log::error($errorMessage, [
                'status_code' => $response->status(),
                'contact_id' => $contactId,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Exception while checking enrichment status', [
                'message' => $e->getMessage(),
                'contact_id' => $contactId,
            ]);
            throw new Exception('Failed to check enrichment status: ' . $e->getMessage());
        }
    }
}
