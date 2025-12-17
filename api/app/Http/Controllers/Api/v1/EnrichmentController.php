<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\EnrichmentRequestResource;
use App\Http\Resources\Api\v1\EnrichmentResultResource;
use App\Models\Contact;
use App\Models\EnrichmentRequest;
use App\Services\ContactEnrichmentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EnrichmentController extends Controller
{
    public function __construct(
        protected ContactEnrichmentService $enrichmentService,
        protected string $defaultTeamName = '',
        protected bool $isDebug = false
    ) {
        $this->defaultTeamName = config('services.primerole.default_team_name');
        $this->isDebug = config('app.debug', false);
    }

    public function enrichContact(Request $request)
    {
        $validator = $this->validateContactEnrichmentRequest($request);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation Failed', 'errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($validator) {
                $enrichmentRequest = $this->enrichmentService->handleEnrichmentRequest($validator->validated());

                return response()->json([
                    'message' => 'Enrichment request queued successfully',
                    'data' => new EnrichmentRequestResource($enrichmentRequest),
                ], 201);
            });
        } catch (Exception $e) {
            Log::error('Failed to process enrichment request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to process the request',
                'error' => $this->isDebug ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    public function show(string $requestId)
    {

        $enrichmentRequest = EnrichmentRequest::with(['result'])->find($requestId);

        if (! $enrichmentRequest) {
            return response()->json(['message' => "Enrichment Request with ID:$requestId not found"], 400);
        }

        $elasticData = $this->fetchElasticData($enrichmentRequest);

        return response()->json([
            'data' => [
                'enrichment_request' => new EnrichmentRequestResource($enrichmentRequest),
                'enrichment_result' => $enrichmentRequest->result ? new EnrichmentResultResource($enrichmentRequest->result) : null,
                'contact' => $elasticData,
            ],
        ]);
    }

    private function fetchElasticData(EnrichmentRequest $enrichmentRequest): mixed
    {
        if (! $enrichmentRequest->result?->reference_id) {
            return null;
        }

        try {
            return Contact::findInElastic($enrichmentRequest->result->reference_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Elasticsearch data', [
                'document_id' => $enrichmentRequest->result->reference_id,
                'request_id' => $enrichmentRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function validateContactEnrichmentRequest(Request $request)
    {
        $rules = [
            'transaction_id' => 'nullable',
            'contact' => 'required|array',
            'company' => 'nullable|array',
            'contact.linkedin_url' => 'nullable|url|required_without:contact.email',
            'contact.email' => 'nullable|email|max:255|required_without:contact.linkedin_url',
            'contact.first_name' => 'nullable|string|required_without_all:contact.email,contact.full_name',
            'contact.last_name' => 'nullable|string|required_without_all:contact.email,contact.full_name',
            'contact.full_name' => 'required_without_all:contact.email,contact.first_name,contact.last_name|string|nullable',
            'company.name' => 'required_without_all:company.domain,contact.email|string|nullable',
            'company.domain' => 'required_without_all:company.name,contact.email|string|nullable',
        ];

        return Validator::make($request->all(), $rules);
    }
}
