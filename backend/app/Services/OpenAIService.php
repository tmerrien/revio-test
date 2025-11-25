<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Service
 *
 * Handles low-level communication with OpenAI API
 */
class OpenAIService
{
    /**
     * The fine-tuned model ID
     *
     * @var string
     */
    private string $model;

    /**
     * Maximum number of retry attempts
     *
     * @var int
     */
    private int $maxRetries;

    /**
     * Temperature for API requests
     *
     * @var float
     */
    private float $temperature;

    /**
     * Maximum tokens in response
     *
     * @var int
     */
    private int $maxTokens;

    /**
     * Create a new service instance.
     */
    public function __construct()
    {
        $this->model = config('openai.fine_tuned_model');
        $this->maxRetries = config('openai.max_retries');
        $this->temperature = config('openai.temperature');
        $this->maxTokens = config('openai.max_tokens');

        if (empty($this->model)) {
            throw new \RuntimeException('OpenAI fine-tuned model not configured. Please set OPENAI_FINE_TUNED_MODEL in .env');
        }
    }

    /**
     * Classify a support ticket and generate a response.
     *
     * @param  string  $ticketText
     * @return array{category: string, response: string}
     * @throws \Exception
     */
    public function classify(string $ticketText): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;

                Log::debug('OpenAI classification attempt', [
                    'attempt' => $attempt,
                    'ticket_length' => strlen($ticketText),
                    'model' => $this->model,
                ]);

                try {
                    $response = OpenAI::chat()->create([
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $ticketText],
                        ],
                        'temperature' => $this->temperature,
                        'max_tokens' => $this->maxTokens,
                    ]);
                } catch (\TypeError $e) {
                    // Handle cases where OpenAI returns a string error instead of array
                    if (str_contains($e->getMessage(), 'CreateResponse::from()')) {
                        throw new \Exception('OpenAI API authentication failed. Please check your OPENAI_API_KEY in .env file.', 0, $e);
                    }
                    throw $e;
                }

                $content = $response->choices[0]->message->content;

                // Parse JSON response
                $result = $this->parseResponse($content);

                Log::info('OpenAI classification successful', [
                    'category' => $result['category'],
                    'response_length' => strlen($result['response']),
                ]);

                return $result;

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('OpenAI classification attempt failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                // Exponential backoff: wait 1s, 2s, 4s...
                if ($attempt < $this->maxRetries) {
                    $waitTime = pow(2, $attempt - 1);
                    sleep($waitTime);
                }
            }
        }

        // All retries exhausted
        Log::error('OpenAI classification failed after all retries', [
            'attempts' => $attempt,
            'error' => $lastException?->getMessage(),
        ]);

        throw new \Exception(
            'OpenAI classification failed after ' . $this->maxRetries . ' attempts: ' . $lastException?->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Build the system prompt with all categories.
     *
     * @return string
     */
    private function buildSystemPrompt(): string
    {
        $categories = [
            'account',
            'billing',
            'cancellation',
            'coach',
            'content',
            'feedback',
            'membership',
            'password',
            'scheduling',
            'technical',
        ];

        $categoriesStr = implode(', ', $categories);

        return "You are a support ticket classifier for a coaching company. " .
               "Classify tickets into one of these categories: {$categoriesStr}. " .
               "Generate a polite, concise, and helpful response addressing the ticket. " .
               "Always respond in valid JSON format with exactly two keys: " .
               "\"category\" (one of the categories above) and \"response\" (your helpful reply). " .
               "Example: {\"category\": \"billing\", \"response\": \"I apologize for the issue...\"}";
    }

    /**
     * Parse the OpenAI response.
     *
     * @param  string  $content
     * @return array{category: string, response: string}
     * @throws \Exception
     */
    private function parseResponse(string $content): array
    {
        // Try to parse as JSON
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            // Validate structure
            if (!isset($decoded['category']) || !isset($decoded['response'])) {
                throw new \Exception('Response missing required keys: category or response');
            }

            // Validate category is not empty
            if (empty($decoded['category']) || empty($decoded['response'])) {
                throw new \Exception('Category or response is empty');
            }

            return [
                'category' => $decoded['category'],
                'response' => $decoded['response'],
            ];

        } catch (\JsonException $e) {
            Log::error('Failed to parse OpenAI response as JSON', [
                'content' => substr($content, 0, 200),
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Invalid JSON response from OpenAI: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the current model ID.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
