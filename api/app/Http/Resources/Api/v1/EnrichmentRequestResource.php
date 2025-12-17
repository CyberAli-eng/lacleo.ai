<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrichmentRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'request_id' => $this->id,
            'request_status' => $this->status,
            'error_message' => $this->error_message,
            'transaction_id' => $this->transaction_id,
            'request_data' => $this->request_data,
        ];
    }
}
