<?php

namespace App\Jobs;

use App\Enums\EnrichmentStatus;
use App\Models\Contact;
use App\Models\EnrichmentRequest;
use App\Services\ContactEnrichmentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorContactEnrichment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected EnrichmentRequest $enrichmentRequest,
        protected string $contactId
    ) {
        $this->timeout = 600;
    }

    public function handle(ContactEnrichmentService $service)
    {
        Log::channel('job')->debug('Starting contact enrichment monitoring', [
            'enrichment_request_id' => $this->enrichmentRequest->id,
            'contact_id' => $this->contactId,
            'retry_count' => $this->enrichmentRequest->retry_count,
        ]);

        try {
            $response = $service->checkEnrichmentStatus($this->contactId);
            if (! $response['success']) {
                return $this->handleFailedStatusCheck($response);
            }

            $contactData = $response['data']['contact'] ?? null;
            if (! $contactData) {
                throw new Exception('Invalid response format: Missing contact data');
            }

            Log::channel('job')->debug('Successfully checked enrichment status', [
                'enrichment_request_id' => $this->enrichmentRequest->id,
                'contact_id' => $this->contactId,
            ]);

            return $this->processEnrichmentTimeline($contactData);

        } catch (Exception $e) {
            $this->enrichmentRequest->updateStatus(
                EnrichmentStatus::FAILED,
                $e->getMessage()
            );
            throw $e;
        }
    }

    protected function processEnrichmentTimeline(array $contactData): void
    {
        $timeline = $contactData['enrichment_timeline'] ?? [];

        if (empty($timeline)) {
            throw new Exception('Invalid response format: Missing enrichment timeline');
        }

        // Analyze timeline statuses
        $hasPending = collect($timeline)->contains('status', 'Pending');
        if (! $hasPending) {
            $this->storeEnrichmentResults($contactData);

            return;
        }

        $this->handlePendingStatus();
    }

    protected function handlePendingStatus(): void
    {
        $this->enrichmentRequest->updateStatus(EnrichmentStatus::PROCESSING);

        Log::channel('job')->debug('Enrichment still in progress, re-dispatching monitor job', [
            'enrichment_request_id' => $this->enrichmentRequest->id,
            'contact_id' => $this->contactId,
            'retry_count' => $this->enrichmentRequest->retry_count,
        ]);

        static::dispatch($this->enrichmentRequest, $this->contactId)
            ->delay(now()->addSeconds(5));
    }

    protected function storeEnrichmentResults(array $contactData): void
    {
        Log::channel('job')->debug('Storing enrichment results', [
            'enrichment_request_id' => $this->enrichmentRequest->id,
            'contact_id' => $this->contactId,
            'contact_data' => $contactData,
        ]);

        // Index to Elasticsearch
        DB::beginTransaction();
        try {
            // Update or create result result
            $result = $this->enrichmentRequest->result()->updateOrCreate(
                ['enrichment_request_id' => $this->enrichmentRequest->id],
                ['raw_response' => ['contact' => $contactData]]
            );

            $contact = Contact::createFromApiResponse($this->contactId, $contactData);

            $result->update(['reference_id' => $this->contactId]);

            $this->enrichmentRequest->updateStatus(EnrichmentStatus::COMPLETED);

            DB::commit();
            Log::channel('job')->debug('Successfully completed enrichment process', [
                'enrichment_request_id' => $this->enrichmentRequest->id,
                'contact' => $contact,
                'contact_id' => $this->contactId,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('job')->error('Failed to index to Elasticsearch', [
                'error' => $e->getMessage(),
                'enrichment_request_id' => $this->enrichmentRequest->id,
            ]);
            throw $e;
        }
    }

    protected function handleFailedStatusCheck(array $response): void
    {
        Log::channel('job')->error('Failed to check enrichment status', [
            'enrichment_request_id' => $this->enrichmentRequest->id,
            'contact_id' => $this->contactId,
            'error' => $response['error'] ?? 'Unknown error',
            'status_code' => $response['status_code'] ?? null,
        ]);

        $this->enrichmentRequest->updateStatus(
            EnrichmentStatus::FAILED,
            $response['error'] ?? 'Unknown error'
        );
    }
}
