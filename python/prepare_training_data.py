#!/usr/bin/env python3
"""
Prepare training data for OpenAI fine-tuning.

Converts CSV dataset to JSONL format with 80/20 train/validation split.
Maintains category balance through stratified splitting.
"""

import json
import pandas as pd
from pathlib import Path
from collections import defaultdict
import random

# Set random seed for reproducibility
random.seed(42)

def load_csv_data(csv_path):
    """Load and validate the CSV dataset."""
    print(f"Loading dataset from: {csv_path}")

    df = pd.read_csv(csv_path)

    # Validate required columns
    required_columns = ['ticket_text', 'category', 'answer']
    if not all(col in df.columns for col in required_columns):
        raise ValueError(f"CSV must contain columns: {required_columns}")

    print(f"✓ Loaded {len(df)} examples")

    # Display category distribution
    category_counts = df['category'].value_counts()
    print(f"✓ Found {len(category_counts)} categories:")
    for category, count in category_counts.items():
        print(f"  - {category}: {count} examples")

    return df

def create_system_prompt(categories):
    """Create a comprehensive system prompt with all categories."""
    categories_str = ", ".join(sorted(categories))

    system_prompt = (
        f"You are a support ticket classifier for a coaching company. "
        f"Classify tickets into one of these categories: {categories_str}. "
        f"Generate a polite, concise, and helpful response addressing the ticket. "
        f"Always respond in valid JSON format with exactly two keys: "
        f'"category" (one of the categories above) and "response" (your helpful reply). '
        f"Example: {{\"category\": \"billing\", \"response\": \"I apologize for the issue...\"}}"
    )

    return system_prompt

def convert_to_messages_format(row, system_prompt):
    """Convert a CSV row to OpenAI's messages format."""

    # Create the assistant response as JSON
    assistant_content = json.dumps({
        "category": row['category'],
        "response": row['answer']
    })

    return {
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": row['ticket_text']},
            {"role": "assistant", "content": assistant_content}
        ]
    }

def stratified_split(df, train_ratio=0.8):
    """
    Perform stratified train/validation split maintaining category balance.

    For a 50-example dataset with 5 examples per category:
    - Train: 4 examples per category (40 total)
    - Validation: 1 example per category (10 total)
    """
    train_data = []
    val_data = []

    # Group by category
    grouped = df.groupby('category')

    for category, group in grouped:
        examples = group.to_dict('records')

        # Shuffle examples within each category
        random.shuffle(examples)

        # Calculate split point
        n_train = int(len(examples) * train_ratio)

        # Split
        train_data.extend(examples[:n_train])
        val_data.extend(examples[n_train:])

        print(f"  {category}: {n_train} train, {len(examples) - n_train} validation")

    return train_data, val_data

def save_jsonl(data, output_path, system_prompt):
    """Save data in JSONL format for OpenAI fine-tuning."""
    output_path = Path(output_path)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    with open(output_path, 'w') as f:
        for row in data:
            messages_format = convert_to_messages_format(row, system_prompt)
            f.write(json.dumps(messages_format) + '\n')

    print(f"✓ Saved {len(data)} examples to {output_path}")

def validate_jsonl(jsonl_path):
    """Validate the JSONL file format."""
    print(f"\nValidating {jsonl_path}...")

    with open(jsonl_path, 'r') as f:
        lines = f.readlines()

    for i, line in enumerate(lines, 1):
        try:
            data = json.loads(line)

            # Check required structure
            if 'messages' not in data:
                raise ValueError(f"Line {i}: Missing 'messages' key")

            messages = data['messages']
            if len(messages) != 3:
                raise ValueError(f"Line {i}: Expected 3 messages, got {len(messages)}")

            # Validate roles
            expected_roles = ['system', 'user', 'assistant']
            actual_roles = [msg['role'] for msg in messages]
            if actual_roles != expected_roles:
                raise ValueError(f"Line {i}: Expected roles {expected_roles}, got {actual_roles}")

            # Validate assistant response is valid JSON
            assistant_content = messages[2]['content']
            assistant_json = json.loads(assistant_content)

            if 'category' not in assistant_json or 'response' not in assistant_json:
                raise ValueError(f"Line {i}: Assistant content must have 'category' and 'response' keys")

        except json.JSONDecodeError as e:
            print(f"✗ Line {i}: Invalid JSON - {e}")
            return False
        except ValueError as e:
            print(f"✗ {e}")
            return False

    print(f"✓ All {len(lines)} lines validated successfully")
    return True

def main():
    # Paths
    project_root = Path(__file__).parent.parent
    csv_path = project_root / "Support Ticket Category - Support_Tickets_with_Answers.csv"
    training_output = Path(__file__).parent / "data" / "training.jsonl"
    validation_output = Path(__file__).parent / "data" / "validation.jsonl"

    print("=" * 60)
    print("OpenAI Fine-Tuning Data Preparation")
    print("=" * 60)

    # Load data
    df = load_csv_data(csv_path)

    # Get unique categories
    categories = sorted(df['category'].unique())

    # Create system prompt
    system_prompt = create_system_prompt(categories)
    print(f"\n✓ System prompt created ({len(system_prompt)} characters)")

    # Perform stratified split
    print(f"\nPerforming stratified split (80/20)...")
    train_data, val_data = stratified_split(df, train_ratio=0.8)
    print(f"✓ Split complete: {len(train_data)} train, {len(val_data)} validation")

    # Save JSONL files
    print(f"\nSaving JSONL files...")
    save_jsonl(train_data, training_output, system_prompt)
    save_jsonl(val_data, validation_output, system_prompt)

    # Validate output
    if validate_jsonl(training_output) and validate_jsonl(validation_output):
        print("\n" + "=" * 60)
        print("✓ Data preparation complete!")
        print("=" * 60)
        print(f"\nNext steps:")
        print(f"1. Review the generated files:")
        print(f"   - {training_output}")
        print(f"   - {validation_output}")
        print(f"2. Run fine_tune.py to start training")
    else:
        print("\n✗ Validation failed. Please check the output files.")
        return 1

    return 0

if __name__ == "__main__":
    exit(main())
