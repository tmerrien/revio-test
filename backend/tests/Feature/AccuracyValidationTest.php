<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use Tests\RefreshMongoDatabase;
use Tests\TestCase;

/**
 * Accuracy Validation Test
 *
 * Validates that the fine-tuned model achieves ≥80% accuracy
 * on the full 50-ticket dataset.
 *
 * IMPORTANT: This test makes REAL API calls to OpenAI and is NOT mocked.
 * Run this only when you have:
 * 1. A fine-tuned model ID configured in .env
 * 2. A valid OpenAI API key
 * 3. The CSV dataset available
 *
 * Usage:
 *   php artisan test --filter AccuracyValidationTest
 */
class AccuracyValidationTest extends TestCase
{
    use RefreshMongoDatabase;

    /**
     * Test that the model achieves at least 80% accuracy.
     */
    #[Group('accuracy')]
    #[Group('slow')]
    public function test_model_achieves_80_percent_accuracy(): void
    {
        // Skip if not in CI or if explicitly testing accuracy
        if (!env('RUN_ACCURACY_TEST', false) && !env('CI', false)) {
            $this->markTestSkipped(
                'Accuracy test skipped. Set RUN_ACCURACY_TEST=true to run this test.'
            );
        }

        // Verify prerequisites
        $this->assertNotEmpty(config('openai.api_key'), 'OpenAI API key not configured');
        $this->assertNotEmpty(config('openai.fine_tuned_model'), 'Fine-tuned model not configured');

        // Load CSV dataset
        $csvPath = base_path('../Support Ticket Category - Support_Tickets_with_Answers.csv');

        if (!file_exists($csvPath)) {
            $this->fail("CSV dataset not found at: {$csvPath}");
        }

        $csv = array_map('str_getcsv', file($csvPath));
        array_shift($csv); // Remove header

        $this->assertGreaterThan(0, count($csv), 'No data in CSV file');

        $correct = 0;
        $total = count($csv);
        $results = [];
        $perCategoryResults = [];

        $this->getOutputWriter()->writeln("\n=== Testing Accuracy on {$total} Examples ===\n");

        foreach ($csv as $index => $row) {
            [$ticketText, $expectedCategory, $expectedAnswer] = $row;

            $this->getOutputWriter()->writeln(sprintf(
                '[%d/%d] Testing: %s...',
                $index + 1,
                $total,
                substr($ticketText, 0, 50)
            ));

            // Make real API call (not mocked)
            $response = $this->postJson('/api/classify', [
                'ticket_text' => $ticketText
            ]);

            $response->assertStatus(201);

            $predictedCategory = $response->json('data.category');
            $isCorrect = ($predictedCategory === $expectedCategory);

            if ($isCorrect) {
                $correct++;
                $this->getOutputWriter()->writeln("  ✓ Correct: {$predictedCategory}");
            } else {
                $this->getOutputWriter()->writeln("  ✗ Wrong: expected '{$expectedCategory}', got '{$predictedCategory}'");
            }

            // Track per-category results
            if (!isset($perCategoryResults[$expectedCategory])) {
                $perCategoryResults[$expectedCategory] = ['correct' => 0, 'total' => 0];
            }
            $perCategoryResults[$expectedCategory]['total']++;
            if ($isCorrect) {
                $perCategoryResults[$expectedCategory]['correct']++;
            }

            $results[] = [
                'ticket_text' => $ticketText,
                'expected_category' => $expectedCategory,
                'predicted_category' => $predictedCategory,
                'is_correct' => $isCorrect,
            ];

            // Small delay to avoid rate limiting
            if ($index < $total - 1) {
                usleep(500000); // 0.5 seconds
            }
        }

        $accuracy = ($correct / $total) * 100;

        // Print detailed report
        $this->getOutputWriter()->writeln("\n" . str_repeat('=', 60));
        $this->getOutputWriter()->writeln("ACCURACY VALIDATION RESULTS");
        $this->getOutputWriter()->writeln(str_repeat('=', 60));
        $this->getOutputWriter()->writeln(sprintf("\nOverall Accuracy: %d/%d (%.1f%%)", $correct, $total, $accuracy));

        if ($accuracy >= 80.0) {
            $this->getOutputWriter()->writeln("✓ PASSED: Accuracy is ≥80% threshold");
        } else {
            $this->getOutputWriter()->writeln("✗ FAILED: Accuracy is below 80% threshold");
        }

        // Per-category breakdown
        $this->getOutputWriter()->writeln("\nPer-Category Accuracy:");
        $this->getOutputWriter()->writeln(str_repeat('-', 60));

        foreach ($perCategoryResults as $category => $stats) {
            $catAccuracy = ($stats['correct'] / $stats['total']) * 100;
            $status = ($stats['correct'] == $stats['total']) ? '✓' : '✗';
            $this->getOutputWriter()->writeln(sprintf(
                "  %s %-15s: %d/%d (%.0f%%)",
                $status,
                $category,
                $stats['correct'],
                $stats['total'],
                $catAccuracy
            ));
        }

        // Misclassifications
        $misclassifications = array_filter($results, fn($r) => !$r['is_correct']);

        if (!empty($misclassifications)) {
            $this->getOutputWriter()->writeln(sprintf("\nMisclassifications (%d):", count($misclassifications)));
            $this->getOutputWriter()->writeln(str_repeat('-', 60));
            foreach ($misclassifications as $result) {
                $this->getOutputWriter()->writeln(sprintf(
                    "\n  Ticket: %s...",
                    substr($result['ticket_text'], 0, 70)
                ));
                $this->getOutputWriter()->writeln(sprintf("  Expected: %s", $result['expected_category']));
                $this->getOutputWriter()->writeln(sprintf("  Predicted: %s", $result['predicted_category']));
            }
        }

        $this->getOutputWriter()->writeln("\n" . str_repeat('=', 60));

        if ($accuracy < 80.0) {
            $this->getOutputWriter()->writeln("RECOMMENDATIONS FOR IMPROVEMENT:");
            $this->getOutputWriter()->writeln(str_repeat('=', 60));
            $this->getOutputWriter()->writeln("1. Prompt Engineering (15 min)");
            $this->getOutputWriter()->writeln("2. Increase Training Epochs (20 min)");
            $this->getOutputWriter()->writeln("3. Data Augmentation (1-2 hours)");
        }

        $this->getOutputWriter()->writeln("");

        // Assert accuracy meets threshold
        $this->assertGreaterThanOrEqual(
            80.0,
            $accuracy,
            sprintf(
                "Model accuracy %.1f%% is below 80%% threshold. %d/%d predictions were correct.",
                $accuracy,
                $correct,
                $total
            )
        );
    }

    /**
     * Get output writer for logging.
     */
    protected function getOutputWriter()
    {
        if (!isset($this->output)) {
            $this->output = new class {
                public function writeln($message) {
                    echo $message . PHP_EOL;
                }
            };
        }

        return $this->output;
    }
}
