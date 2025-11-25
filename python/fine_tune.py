#!/usr/bin/env python3
"""
Fine-tune OpenAI model for support ticket classification.

Uploads training data to OpenAI and creates a fine-tuning job.
Monitors progress and saves the fine-tuned model ID.
"""

import os
import time
import json
from pathlib import Path
from openai import OpenAI
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

def check_api_key():
    """Verify OpenAI API key is set."""
    api_key = os.getenv('OPENAI_API_KEY')

    if not api_key:
        print("✗ Error: OPENAI_API_KEY not found in environment")
        print("\nPlease set your API key:")
        print("  export OPENAI_API_KEY='sk-...'")
        print("\nOr create a .env file:")
        print("  echo 'OPENAI_API_KEY=sk-...' > .env")
        return False

    if not api_key.startswith('sk-'):
        print(f"✗ Warning: API key doesn't start with 'sk-', got: {api_key[:10]}...")
        return False

    print(f"✓ API key found: {api_key[:10]}...{api_key[-4:]}")
    return True

def upload_training_file(client, file_path):
    """Upload the training JSONL file to OpenAI."""
    print(f"\nUploading training file: {file_path}")

    if not file_path.exists():
        raise FileNotFoundError(f"Training file not found: {file_path}")

    # Check file size
    file_size_mb = file_path.stat().st_size / (1024 * 1024)
    print(f"  File size: {file_size_mb:.2f} MB")

    with open(file_path, 'rb') as f:
        response = client.files.create(
            file=f,
            purpose='fine-tune'
        )

    print(f"✓ File uploaded successfully")
    print(f"  File ID: {response.id}")
    print(f"  Status: {response.status}")

    return response.id

def create_fine_tuning_job(client, training_file_id):
    """Create and start the fine-tuning job."""
    print(f"\nCreating fine-tuning job...")

    # Hyperparameters optimized for small dataset (50 examples)
    hyperparameters = {
        "n_epochs": 3,                     # Conservative to prevent overfitting
        "batch_size": 1,                   # Small batch size for small dataset
        "learning_rate_multiplier": 0.1    # Lower learning rate
    }

    print(f"  Base model: gpt-3.5-turbo")
    print(f"  Hyperparameters:")
    for key, value in hyperparameters.items():
        print(f"    - {key}: {value}")

    response = client.fine_tuning.jobs.create(
        training_file=training_file_id,
        model="gpt-3.5-turbo",
        hyperparameters=hyperparameters
    )

    print(f"✓ Fine-tuning job created")
    print(f"  Job ID: {response.id}")
    print(f"  Status: {response.status}")

    return response.id

def monitor_fine_tuning_job(client, job_id):
    """Monitor the fine-tuning job progress."""
    print(f"\nMonitoring fine-tuning job: {job_id}")
    print("This may take 10-20 minutes for ~50 examples...")
    print("-" * 60)

    last_status = None
    check_count = 0

    while True:
        check_count += 1

        # Get job status
        job = client.fine_tuning.jobs.retrieve(job_id)

        # Print status update if changed
        if job.status != last_status:
            print(f"\n[{time.strftime('%H:%M:%S')}] Status: {job.status}")
            last_status = job.status

            # Print additional info
            if job.trained_tokens:
                print(f"  Trained tokens: {job.trained_tokens:,}")

            if hasattr(job, 'error') and job.error:
                print(f"  Error: {job.error}")

        # Check if job is complete
        if job.status == 'succeeded':
            print("\n" + "=" * 60)
            print("✓ Fine-tuning completed successfully!")
            print("=" * 60)
            print(f"\nFine-tuned Model ID: {job.fine_tuned_model}")
            print(f"Training Duration: ~{check_count} minutes")

            if job.trained_tokens:
                print(f"Total Tokens Trained: {job.trained_tokens:,}")

            return job.fine_tuned_model

        elif job.status == 'failed':
            print("\n✗ Fine-tuning job failed")
            if hasattr(job, 'error') and job.error:
                print(f"Error details: {job.error}")
            return None

        elif job.status == 'cancelled':
            print("\n✗ Fine-tuning job was cancelled")
            return None

        # Print progress indicator
        print(".", end="", flush=True)

        # Wait before next check
        time.sleep(60)  # Check every minute

def save_model_id(model_id, output_path):
    """Save the fine-tuned model ID to a file."""
    output_path = Path(output_path)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    # Save as plain text
    with open(output_path, 'w') as f:
        f.write(model_id)

    print(f"\n✓ Model ID saved to: {output_path}")

    # Also save metadata
    metadata_path = output_path.parent / "fine_tuned_metadata.json"
    metadata = {
        "model_id": model_id,
        "base_model": "gpt-3.5-turbo",
        "created_at": time.strftime('%Y-%m-%d %H:%M:%S'),
        "training_examples": 40,
        "validation_examples": 10,
        "hyperparameters": {
            "n_epochs": 3,
            "batch_size": 1,
            "learning_rate_multiplier": 0.1
        }
    }

    with open(metadata_path, 'w') as f:
        json.dump(metadata, f, indent=2)

    print(f"✓ Metadata saved to: {metadata_path}")

def estimate_cost():
    """Estimate the cost of fine-tuning."""
    # As of 2024, gpt-3.5-turbo fine-tuning costs:
    # Training: $0.008 per 1K tokens
    # Assume ~100 tokens per example (ticket + response)
    # 40 examples * 100 tokens * 3 epochs = 12,000 tokens
    training_tokens = 40 * 100 * 3
    training_cost = (training_tokens / 1000) * 0.008

    print(f"\nEstimated Cost:")
    print(f"  Training tokens: ~{training_tokens:,}")
    print(f"  Training cost: ${training_cost:.4f}")
    print(f"  (This is an estimate; actual cost may vary)")

def main():
    print("=" * 60)
    print("OpenAI Fine-Tuning Job Creation")
    print("=" * 60)

    # Check API key
    if not check_api_key():
        return 1

    # Initialize OpenAI client
    client = OpenAI(api_key=os.getenv('OPENAI_API_KEY'))

    # Paths
    training_file = Path(__file__).parent / "data" / "training.jsonl"
    model_id_file = Path(__file__).parent / "models" / "fine_tuned_model_id.txt"

    # Check if training file exists
    if not training_file.exists():
        print(f"\n✗ Training file not found: {training_file}")
        print("\nPlease run prepare_training_data.py first:")
        print("  python prepare_training_data.py")
        return 1

    # Display cost estimate
    estimate_cost()

    # Confirm before proceeding
    print("\n" + "-" * 60)
    response = input("Proceed with fine-tuning? (y/n): ")
    if response.lower() != 'y':
        print("Fine-tuning cancelled.")
        return 0

    try:
        # Upload training file
        training_file_id = upload_training_file(client, training_file)

        # Create fine-tuning job
        job_id = create_fine_tuning_job(client, training_file_id)

        # Monitor job progress
        model_id = monitor_fine_tuning_job(client, job_id)

        if model_id:
            # Save model ID
            save_model_id(model_id, model_id_file)

            print("\n" + "=" * 60)
            print("Next Steps:")
            print("=" * 60)
            print(f"1. Copy your model ID to Laravel .env file:")
            print(f"   OPENAI_FINE_TUNED_MODEL={model_id}")
            print(f"\n2. Test the model:")
            print(f"   python validate_model.py")
            print(f"\n3. Start building the Laravel backend")

            return 0
        else:
            print("\n✗ Fine-tuning failed")
            return 1

    except Exception as e:
        print(f"\n✗ Error: {e}")
        import traceback
        traceback.print_exc()
        return 1

if __name__ == "__main__":
    exit(main())
