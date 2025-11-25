<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket Response Resource
 *
 * Transforms ticket model data into JSON API response
 */
class TicketResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->_id,
            'ticket_text' => $this->ticket_text,
            'category' => $this->predicted_category,
            'response' => $this->predicted_response,
            'confidence_score' => $this->confidence_score,
            'processing_time_ms' => $this->processing_time_ms,
            'model_used' => $this->when($request->input('include_model'), $this->model_used),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
