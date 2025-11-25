<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassifyTicketRequest;
use App\Http\Resources\TicketResponseResource;
use App\Models\Ticket;
use App\Services\TicketClassifierService;

/**
 * Ticket Controller
 *
 * Handles API endpoints for ticket classification
 */
class TicketController extends Controller
{
    /**
     * Ticket classifier service
     *
     * @var TicketClassifierService
     */
    private TicketClassifierService $classifierService;

    /**
     * Create a new controller instance.
     *
     * @param  TicketClassifierService  $classifierService
     */
    public function __construct(TicketClassifierService $classifierService)
    {
        $this->classifierService = $classifierService;
    }

    /**
     * Classify a support ticket and generate a response.
     *
     * @param  ClassifyTicketRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function classify(ClassifyTicketRequest $request): \Illuminate\Http\JsonResponse
    {
        $ticket = $this->classifierService->classifyAndRespond(
            $request->input('ticket_text')
        );

        return (new TicketResponseResource($ticket))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a specific ticket by ID.
     *
     * @param  string  $id
     * @return TicketResponseResource
     */
    public function show(string $id): TicketResponseResource
    {
        $ticket = Ticket::findOrFail($id);

        return new TicketResponseResource($ticket);
    }

    /**
     * List all tickets with pagination.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $tickets = Ticket::latest()->paginate(20);

        return TicketResponseResource::collection($tickets);
    }

    /**
     * Get classification statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        $stats = $this->classifierService->getStatistics();

        return response()->json([
            'data' => $stats
        ]);
    }
}
