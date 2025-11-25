#!/usr/bin/env python3
"""
Validate fine-tuned model accuracy against validation set.

Tests the fine-tuned model against the validation dataset and calculates:
- Overall accuracy
- Per-category accuracy
- Confusion matrix (optional)
"""

import os
import json
import time
from pathlib import Path
from collections import defaultdict
from openai import OpenAI
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

def load_model_id():
    """Load the fine-tuned model ID from file."""
    model_id_file = Path(__file__).parent / "models" / "fine_tuned_model_id.txt"

    if not model_id_file.exists():
        print(f"✗ Model ID file not found: {model_id_file}")
        print("\nPlease run fine_tune.py first to create a fine-tuned model")
        return None

    with open(model_id_file, 'r') as f:
        model_id = f.read().strip()

    print(f"✓ Loaded model ID: {model_id}")
    return model_id

def load_validation_data():
    """Load the validation JSONL file."""
    val_file = Path(__file__).parent / "data" / "validation.jsonl"

    if not val_file.exists():
        print(f"✗ Validation file not found: {val_file}")
        print("\nPlease run prepare_training_data.py first")
        return None

    validation_examples = []

    with open(val_file, 'r') as f:
        for line in f:
            data = json.loads(line)
            messages = data['messages']

            # Extract ticket text (user message)
            ticket_text = messages[1]['content']

            # Extract expected category and response (assistant message)
            assistant_content = json.loads(messages[2]['content'])
            expected_category = assistant_content['category']
            expected_response = assistant_content['response']

            validation_examples.append({
                'ticket_text': ticket_text,
                'expected_category': expected_category,
                'expected_response': expected_response
            })

    print(f"✓ Loaded {len(validation_examples)} validation examples")
    return validation_examples

def classify_ticket(client, model_id, ticket_text, system_prompt):
    """Classify a single ticket using the fine-tuned model."""
    try:
        response = client.chat.completions.create(
            model=model_id,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": ticket_text}
            ],
            temperature=0.3,  # Low temperature for consistency
            max_tokens=500
        )

        content = response.choices[0].message.content

        # Parse JSON response
        try:
            result = json.loads(content)
            return result.get('category'), result.get('response')
        except json.JSONDecodeError:
            print(f"  Warning: Could not parse JSON response: {content[:100]}...")
            return None, None

    except Exception as e:
        print(f"  Error classifying ticket: {e}")
        return None, None

def validate_model(client, model_id, validation_examples):
    """Validate the model against all validation examples."""
    print(f"\nValidating model against {len(validation_examples)} examples...")
    print("-" * 60)

    # System prompt (should match training)
    system_prompt = (
        "You are a support ticket classifier for a coaching company. "
        "Classify tickets into one of these categories: account, billing, "
        "cancellation, coach, content, feedback, membership, password, "
        "scheduling, technical. "
        "Generate a polite, concise, and helpful response addressing the ticket. "
        "Always respond in valid JSON format with exactly two keys: "
        '"category" (one of the categories above) and "response" (your helpful reply).'
    )

    correct = 0
    total = len(validation_examples)
    results = []
    per_category_stats = defaultdict(lambda: {'correct': 0, 'total': 0})

    for i, example in enumerate(validation_examples, 1):
        ticket_text = example['ticket_text']
        expected_category = example['expected_category']

        print(f"\n[{i}/{total}] Testing: {ticket_text[:60]}...")

        # Classify
        predicted_category, predicted_response = classify_ticket(
            client, model_id, ticket_text, system_prompt
        )

        # Check if correct
        is_correct = (predicted_category == expected_category)

        if is_correct:
            correct += 1
            print(f"  ✓ Correct: {predicted_category}")
        else:
            print(f"  ✗ Wrong: expected '{expected_category}', got '{predicted_category}'")

        # Update per-category stats
        per_category_stats[expected_category]['total'] += 1
        if is_correct:
            per_category_stats[expected_category]['correct'] += 1

        # Store result
        results.append({
            'ticket_text': ticket_text,
            'expected_category': expected_category,
            'predicted_category': predicted_category,
            'is_correct': is_correct,
            'predicted_response': predicted_response
        })

        # Rate limiting: small delay between requests
        if i < total:
            time.sleep(1)

    return results, correct, total, per_category_stats

def print_validation_report(results, correct, total, per_category_stats):
    """Print a comprehensive validation report."""
    accuracy = (correct / total) * 100

    print("\n" + "=" * 60)
    print("VALIDATION RESULTS")
    print("=" * 60)

    # Overall accuracy
    print(f"\nOverall Accuracy: {correct}/{total} ({accuracy:.1f}%)")

    # Pass/Fail based on 80% threshold
    if accuracy >= 80.0:
        print(f"✓ PASSED: Accuracy is ≥80% threshold")
    else:
        print(f"✗ FAILED: Accuracy is below 80% threshold")

    # Per-category breakdown
    print(f"\nPer-Category Accuracy:")
    print("-" * 60)

    for category in sorted(per_category_stats.keys()):
        stats = per_category_stats[category]
        cat_accuracy = (stats['correct'] / stats['total']) * 100 if stats['total'] > 0 else 0
        status = "✓" if stats['correct'] == stats['total'] else "✗"
        print(f"  {status} {category:15s}: {stats['correct']}/{stats['total']} ({cat_accuracy:.0f}%)")

    # Misclassifications
    misclassifications = [r for r in results if not r['is_correct']]

    if misclassifications:
        print(f"\nMisclassifications ({len(misclassifications)}):")
        print("-" * 60)
        for result in misclassifications:
            print(f"\n  Ticket: {result['ticket_text'][:70]}...")
            print(f"  Expected: {result['expected_category']}")
            print(f"  Predicted: {result['predicted_category']}")

    # Recommendations
    print("\n" + "=" * 60)
    if accuracy < 80.0:
        print("RECOMMENDATIONS FOR IMPROVEMENT:")
        print("=" * 60)
        print("1. Prompt Engineering (15 min):")
        print("   - Add few-shot examples to system prompt")
        print("   - Include 1-2 examples per category")
        print("\n2. Increase Training Epochs (20 min):")
        print("   - Try 5-6 epochs in fine_tune.py")
        print("   - Monitor validation loss for overfitting")
        print("\n3. Data Augmentation (1-2 hours):")
        print("   - Use GPT-4 to generate paraphrases")
        print("   - Expand dataset from 50 to 150 examples")
        print("\n4. Confidence Thresholding (30 min):")
        print("   - Return generic response for low-confidence predictions")
    else:
        print("✓ Model meets accuracy requirements!")
        print("=" * 60)
        print("\nNext Steps:")
        print("1. Copy model ID to Laravel .env file")
        print("2. Start building the Laravel backend")
        print("3. Run Laravel accuracy tests to confirm")

def main():
    print("=" * 60)
    print("Fine-Tuned Model Validation")
    print("=" * 60)

    # Check API key
    api_key = os.getenv('OPENAI_API_KEY')
    if not api_key:
        print("\n✗ OPENAI_API_KEY not found in environment")
        print("Please set your API key: export OPENAI_API_KEY='sk-...'")
        return 1

    # Initialize client
    client = OpenAI(api_key=api_key)

    # Load model ID
    model_id = load_model_id()
    if not model_id:
        return 1

    # Load validation data
    validation_examples = load_validation_data()
    if not validation_examples:
        return 1

    # Validate model
    try:
        results, correct, total, per_category_stats = validate_model(
            client, model_id, validation_examples
        )

        # Print report
        print_validation_report(results, correct, total, per_category_stats)

        # Save results
        results_file = Path(__file__).parent / "models" / "validation_results.json"
        with open(results_file, 'w') as f:
            json.dump({
                'accuracy': (correct / total) * 100,
                'correct': correct,
                'total': total,
                'per_category_stats': dict(per_category_stats),
                'results': results
            }, f, indent=2)

        print(f"\n✓ Detailed results saved to: {results_file}")

        # Return exit code based on accuracy
        accuracy = (correct / total) * 100
        return 0 if accuracy >= 80.0 else 1

    except Exception as e:
        print(f"\n✗ Validation error: {e}")
        import traceback
        traceback.print_exc()
        return 1

if __name__ == "__main__":
    exit(main())
