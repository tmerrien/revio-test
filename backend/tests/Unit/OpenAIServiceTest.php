<?php

namespace Tests\Unit;

use App\Services\OpenAIService;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class OpenAIServiceTest extends TestCase
{
    public function test_classify_returns_valid_structure(): void
    {
        // Mock OpenAI response
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'billing',
                                'response' => 'Test response'
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $service = new OpenAIService();
        $result = $service->classify('Test ticket');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertEquals('billing', $result['category']);
        $this->assertEquals('Test response', $result['response']);
    }

    public function test_classify_handles_invalid_json_response(): void
    {
        // Mock OpenAI with invalid JSON (provide 3 responses for retry logic)
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    ['message' => ['content' => 'This is not valid JSON']]
                ]
            ]),
            CreateResponse::fake([
                'choices' => [
                    ['message' => ['content' => 'This is not valid JSON']]
                ]
            ]),
            CreateResponse::fake([
                'choices' => [
                    ['message' => ['content' => 'This is not valid JSON']]
                ]
            ])
        ]);

        $service = new OpenAIService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $service->classify('Test ticket');
    }

    public function test_classify_handles_missing_category(): void
    {
        // Mock OpenAI response missing category (provide 3 responses for retry logic)
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['response' => 'Test response'])
                        ]
                    ]
                ]
            ]),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['response' => 'Test response'])
                        ]
                    ]
                ]
            ]),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['response' => 'Test response'])
                        ]
                    ]
                ]
            ])
        ]);

        $service = new OpenAIService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('missing required keys');

        $service->classify('Test ticket');
    }

    public function test_classify_retries_on_failure(): void
    {
        // Mock OpenAI to fail twice, then succeed
        OpenAI::fake([
            new \Exception('API Error'),
            new \Exception('API Error'),
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'technical',
                                'response' => 'Success after retry'
                            ])
                        ]
                    ]
                ]
            ])
        ]);

        $service = new OpenAIService();
        $result = $service->classify('Test ticket');

        $this->assertEquals('technical', $result['category']);
        $this->assertEquals('Success after retry', $result['response']);
    }

    public function test_get_model_returns_configured_model(): void
    {
        config(['openai.fine_tuned_model' => 'ft:gpt-3.5-turbo-test']);

        $service = new OpenAIService();

        $this->assertEquals('ft:gpt-3.5-turbo-test', $service->getModel());
    }
}
