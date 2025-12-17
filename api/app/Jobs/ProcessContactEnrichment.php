<?php

namespace App\Jobs;

use App\Enums\EnrichmentStatus;
use App\Models\EnrichmentRequest;
use App\Services\ContactEnrichmentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContactEnrichment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected EnrichmentRequest $enrichmentRequest
    ) {}

    public function handle(ContactEnrichmentService $service)
    {
        Log::channel('job')->debug('Starting contact enrichment process', [
            'enrichment_request_id' => $this->enrichmentRequest->id,
            'transaction_id' => $this->enrichmentRequest->request_data['transaction_id'] ?? null,
            'request_data' => $this->enrichmentRequest->request_data,
        ]);

        try {
            $this->enrichmentRequest->increment('retry_count');
            // Update status to NOT_STARTED
            $this->enrichmentRequest->updateStatus(EnrichmentStatus::NOT_STARTED);

            // Create contact in PrimeRole
            $createResponse = $service->createPrimeRoleContact($this->enrichmentRequest->request_data);
            if (! $createResponse['success']) {
                throw new Exception('Failed to create contact: '.($createResponse['error'] ?? 'Unknown error'));
            }

            $contactId = $createResponse['data']['records'][0]['contact']['id'] ?? null;
            if (! $contactId) {
                throw new Exception('Contact ID not found in PrimeRole response');
            }

            Log::channel('job')->debug('Successfully created contact in PrimeRole', [
                'enrichment_request_id' => $this->enrichmentRequest->id,
                'contact_id' => $contactId,
            ]);

            // Start enrichment
            $this->enrichmentRequest->updateStatus(EnrichmentStatus::PENDING);

            $enrichResponse = $service->startEnrichment($contactId);
            if (! $enrichResponse['success']) {
                throw new Exception('Failed to start enrichment: '.($enrichResponse['error'] ?? 'Unknown error'));
            }

            Log::channel('job')->debug('Enrichment response structure', [
                'response' => $enrichResponse,
                'response_type' => gettype($enrichResponse),
                'data_type' => gettype($enrichResponse['data']),
            ]);

            // Create initial result
            $result = $this->enrichmentRequest->result()->create([
                'raw_response' => $enrichResponse['data'],
            ]);

            Log::channel('job')->debug('Successfully started enrichment process', [
                'enrichment_request_id' => $this->enrichmentRequest->id,
                'contact_id' => $contactId,
                'result_id' => $result->id,
            ]);

            // Dispatch monitoring job
            MonitorContactEnrichment::dispatch($this->enrichmentRequest, $contactId);

        } catch (Exception $e) {
            $this->enrichmentRequest->updateStatus(
                EnrichmentStatus::FAILED,
                $e->getMessage()
            );

            Log::channel('job')->error('Failed to process contact enrichment', [
                'enrichment_request_id' => $this->enrichmentRequest->id,
                'job' => self::class,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'jobId' => $this->job->getJobId(),
            ]);

            throw $e;
        }
    }
}
