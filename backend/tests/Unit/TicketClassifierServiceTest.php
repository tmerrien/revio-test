<?php

namespace Tests\Unit;

use App\Exceptions\ClassificationException;
use App\Models\Ticket;
use App\Services\OpenAIService;
use App\Services\TicketClassifierService;
use Mockery;
use Tests\RefreshMongoDatabase;
use Tests\TestCase;

class TicketClassifierServiceTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_classify_and_respond_creates_ticket(): void
    {
        // Mock OpenAIService
        $openAIService = Mockery::mock(OpenAIService::class);
        $openAIService->shouldReceive('classify')
            ->once()
            ->with('Test ticket text')
            ->andReturn([
                'category' => 'billing',
                'response' => 'Test response'
            ]);
        $openAIService->shouldReceive('getModel')
            ->andReturn('ft:gpt-3.5-turbo-test');

        $service = new TicketClassifierService($openAIService);
        $ticket = $service->classifyAndRespond('Test ticket text');

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertEquals('Test ticket text', $ticket->ticket_text);
        $this->assertEquals('billing', $ticket->predicted_category);
        $this->assertEquals('Test response', $ticket->predicted_response);
        $this->assertNotNull($ticket->processing_time_ms);
        $this->assertEquals('ft:gpt-3.5-turbo-test', $ticket->model_used);
    }

    public function test_classify_and_respond_throws_exception_on_failure(): void
    {
        // Mock OpenAIService to throw exception
        $openAIService = Mockery::mock(OpenAIService::class);
        $openAIService->shouldReceive('classify')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $service = new TicketClassifierService($openAIService);

        $this->expectException(ClassificationException::class);
        $this->expectExceptionMessage('Failed to classify ticket');

        $service->classifyAndRespond('Test ticket text');
    }

    public function test_get_statistics_returns_correct_data(): void
    {
        // Create some test tickets
        Ticket::create([
            'ticket_text' => 'Test 1',
            'predicted_category' => 'billing',
            'predicted_response' => 'Response 1',
            'processing_time_ms' => 100,
        ]);

        Ticket::create([
            'ticket_text' => 'Test 2',
            'predicted_category' => 'billing',
            'predicted_response' => 'Response 2',
            'processing_time_ms' => 200,
        ]);

        Ticket::create([
            'ticket_text' => 'Test 3',
            'predicted_category' => 'technical',
            'predicted_response' => 'Response 3',
            'processing_time_ms' => 150,
        ]);

        $openAIService = Mockery::mock(OpenAIService::class);
        $service = new TicketClassifierService($openAIService);

        $stats = $service->getStatistics();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['by_category']['billing']);
        $this->assertEquals(1, $stats['by_category']['technical']);
        $this->assertEquals(150.0, $stats['avg_processing_time']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
