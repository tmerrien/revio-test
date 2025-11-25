<?php

namespace Tests\Feature;

use App\Models\Ticket;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\RefreshMongoDatabase;
use Tests\TestCase;

class ClassifyTicketTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_classify_endpoint_returns_successful_response(): void
    {
        // Mock OpenAI response
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'billing',
                                'response' => 'I apologize for the billing issue. I will look into this immediately.'
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $response = $this->postJson('/api/classify', [
            'ticket_text' => 'I was charged twice for my membership.'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ticket_text',
                    'category',
                    'response',
                    'processing_time_ms',
                    'created_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'ticket_text' => 'I was charged twice for my membership.',
                    'category' => 'billing',
                ]
            ]);

        // Verify ticket was saved to database
        $this->assertDatabaseHas('tickets', [
            'ticket_text' => 'I was charged twice for my membership.',
            'predicted_category' => 'billing',
        ]);
    }

    public function test_classify_validates_minimum_length(): void
    {
        $response = $this->postJson('/api/classify', [
            'ticket_text' => 'Short'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_text']);
    }

    public function test_classify_validates_maximum_length(): void
    {
        $response = $this->postJson('/api/classify', [
            'ticket_text' => str_repeat('a', 2001)
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_text']);
    }

    public function test_classify_requires_ticket_text(): void
    {
        $response = $this->postJson('/api/classify', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_text']);
    }

    public function test_get_ticket_by_id(): void
    {
        // Create a test ticket
        $ticket = Ticket::create([
            'ticket_text' => 'Test ticket',
            'predicted_category' => 'billing',
            'predicted_response' => 'Test response',
            'processing_time_ms' => 100,
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->_id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $ticket->_id,
                    'ticket_text' => 'Test ticket',
                    'category' => 'billing',
                ]
            ]);
    }

    public function test_get_ticket_by_id_returns_404_for_invalid_id(): void
    {
        $response = $this->getJson('/api/tickets/invalid-id');

        $response->assertStatus(404);
    }

    public function test_list_tickets_with_pagination(): void
    {
        // Create multiple test tickets
        for ($i = 0; $i < 25; $i++) {
            Ticket::create([
                'ticket_text' => "Test ticket {$i}",
                'predicted_category' => 'billing',
                'predicted_response' => "Test response {$i}",
                'processing_time_ms' => 100,
            ]);
        }

        $response = $this->getJson('/api/tickets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ticket_text',
                        'category',
                        'response',
                    ]
                ],
                'links',
                'meta'
            ]);

        // Check pagination
        $this->assertCount(20, $response->json('data')); // Default page size
    }

    public function test_get_statistics(): void
    {
        // Create test tickets
        Ticket::create([
            'ticket_text' => 'Test 1',
            'predicted_category' => 'billing',
            'predicted_response' => 'Response 1',
            'processing_time_ms' => 100,
        ]);

        Ticket::create([
            'ticket_text' => 'Test 2',
            'predicted_category' => 'technical',
            'predicted_response' => 'Response 2',
            'processing_time_ms' => 200,
        ]);

        $response = $this->getJson('/api/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'by_category',
                    'avg_processing_time',
                    'period_days'
                ]
            ])
            ->assertJson([
                'data' => [
                    'total' => 2,
                ]
            ]);
    }
}
