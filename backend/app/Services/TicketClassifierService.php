<?php

namespace App\Services;

use App\Models\Ticket;
use App\Exceptions\ClassificationException;
use Illuminate\Support\Facades\Log;

/**
 * Ticket Classifier Service
 *
 * Orchestrates the business logic for classifying tickets and persisting results
 */
class TicketClassifierService
{
    /**
     * OpenAI service instance
     *
     * @var OpenAIService
     */
    private OpenAIService $openAIService;

    /**
     * Create a new service instance.
     *
     * @param  OpenAIService  $openAIService
     */
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Classify a support ticket and generate a response.
     *
     * This method:
     * 1. Calls OpenAI API to classify and respond
     * 2. Persists the result to MongoDB
     * 3. Returns the ticket record
     *
     * @param  string  $ticketText
     * @return Ticket
     * @throws ClassificationException
     */
    public function classifyAndRespond(string $ticketText): Ticket
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting ticket classification', [
                'ticket_length' => strlen($ticketText),
            ]);

            // Call OpenAI for classification and response
            $result = $this->openAIService->classify($ticketText);

            // Validate response structure
            if (!isset($result['category']) || !isset($result['response'])) {
                throw new ClassificationException('Invalid response format from OpenAI service');
            }

            // Calculate processing time
            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Persist to MongoDB
            $ticket = Ticket::create([
                'ticket_text' => $ticketText,
                'predicted_category' => $result['category'],
                'predicted_response' => $result['response'],
                'confidence_score' => $result['confidence'] ?? null,
                'processing_time_ms' => round($processingTime, 2),
                'model_used' => $this->openAIService->getModel(),
            ]);

            Log::info('Ticket classification completed', [
                'ticket_id' => $ticket->_id,
                'category' => $result['category'],
                'processing_time_ms' => $processingTime,
            ]);

            return $ticket;

        } catch (\Exception $e) {
            Log::error('Ticket classification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ClassificationException(
                'Failed to classify ticket: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get statistics for classified tickets.
     *
     * @param  int  $days  Number of days to look back
     * @return array{total: int, by_category: array, avg_processing_time: float}
     */
    public function getStatistics(int $days = 7): array
    {
        $tickets = Ticket::recent($days)->get();

        $byCategory = $tickets->groupBy('predicted_category')
            ->map(fn($group) => $group->count())
            ->toArray();

        $avgProcessingTime = $tickets->avg('processing_time_ms');

        return [
            'total' => $tickets->count(),
            'by_category' => $byCategory,
            'avg_processing_time' => round($avgProcessingTime ?? 0, 2),
            'period_days' => $days,
        ];
    }

    /**
     * Bulk classify multiple tickets.
     *
     * @param  array<string>  $ticketTexts
     * @return array<Ticket>
     */
    public function classifyBatch(array $ticketTexts): array
    {
        $results = [];

        foreach ($ticketTexts as $ticketText) {
            try {
                $results[] = $this->classifyAndRespond($ticketText);
            } catch (ClassificationException $e) {
                Log::warning('Batch classification item failed', [
                    'ticket_text' => substr($ticketText, 0, 100),
                    'error' => $e->getMessage(),
                ]);
                // Continue with next ticket instead of failing entire batch
                continue;
            }
        }

        return $results;
    }
}
