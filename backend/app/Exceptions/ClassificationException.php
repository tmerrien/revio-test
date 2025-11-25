<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Classification Exception
 *
 * Thrown when ticket classification fails
 */
class ClassificationException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        // Exception will be logged automatically by Laravel
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function render(Request $request): JsonResponse
    {
        $status = 500;
        $message = $this->getMessage() ?: 'Classification failed';

        // Check for specific error types
        if (str_contains($message, 'API key')) {
            $status = 401;
        } elseif (str_contains($message, 'rate limit')) {
            $status = 429;
        } elseif (str_contains($message, 'timeout')) {
            $status = 504;
        }

        return response()->json([
            'error' => 'Classification Error',
            'message' => $message,
            'status' => $status,
        ], $status);
    }
}
