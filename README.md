# AI-Powered Support Ticket Classifier & Responder

A microservice that automatically classifies support tickets into 10 predefined categories and generates context-appropriate first-touch responses using OpenAI fine-tuning.

## Features

- **10-Category Classification**: billing, technical, content, scheduling, cancellation, feedback, account, password, coach, membership
- **AI-Generated Responses**: Polite, concise, and contextually appropriate replies
- **MongoDB Persistence**: All classifications and responses stored for analysis
- **RESTful API**: Clean JSON API with comprehensive error handling
- **≥80% Accuracy**: Validated against 50-ticket test dataset
- **Comprehensive Testing**: PHPUnit tests with mocked and real API calls
- **CI/CD Pipeline**: Automated testing via GitHub Actions

## Tech Stack

- **Backend**: Laravel 11 + PHP 8.3
- **Database**: MongoDB 8.2
- **AI**: OpenAI Fine-Tuning (gpt-3.5-turbo)
- **Testing**: PHPUnit
- **CI/CD**: GitHub Actions

---

## Quick Start

### Prerequisites

- PHP 8.3+
- Composer
- Python 3.12+
- **MongoDB 8.2+ (must be installed and running)**
- OpenAI API key ([Get one here](https://platform.openai.com/api-keys))

---

### Step 1: Clone Repository

```bash
git clone https://github.com/tmerrien/revio-test.git
cd revio-test
```

---

### Step 2: Fine-Tune OpenAI Model

```bash
cd python

# Create virtual environment
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Set your OpenAI API key
export OPENAI_API_KEY="sk-your-api-key-here"

# Prepare training data (converts CSV to JSONL)
python prepare_training_data.py

# Run fine-tuning (takes ~10-20 minutes)
python fine_tune.py

# Validate model accuracy
python validate_model.py
```

**Save the fine-tuned model ID** from the output (e.g., `ft:gpt-3.5-turbo-xxx`).

---

### Step 3: Setup Laravel Backend

```bash
cd ../backend

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env

# Update .env with your values:
# OPENAI_API_KEY=sk-...
# OPENAI_FINE_TUNED_MODEL=ft:gpt-3.5-turbo-xxx

# Generate application key
php artisan key:generate

# Ensure MongoDB is running (required!)
# Quick start: mongod --dbpath ~/data/db

# Start Laravel server
php artisan serve
```

---

### Step 4: Test

```bash
curl -X POST http://localhost:8000/api/classify \
  -H "Content-Type: application/json" \
  -d '{
    "ticket_text": "I was charged twice for this months membership."
  }'
```

**Expected Response**:
```json
{
  "data": {
    "id": "507f1f77bcf86cd799439011",
    "ticket_text": "I was charged twice for this months membership.",
    "category": "billing",
    "response": "I'm sorry for the duplicate charge. I've processed a refund...",
    "processing_time_ms": 1234,
    "created_at": "2025-11-24T10:30:00Z"
  }
}
```

---

## API Documentation

### Classify Ticket

**Endpoint**: `POST /api/classify`

**Request**:
```json
{
  "ticket_text": "Your support ticket text here (10-2000 chars)"
}
```

**Response**:
```json
{
  "data": {
    "id": "507f1f77bcf86cd799439011",
    "ticket_text": "I was charged twice...",
    "category": "billing",
    "response": "I'm sorry for the duplicate charge...",
    "confidence_score": null,
    "processing_time_ms": 1234,
    "created_at": "2025-11-24T10:30:00Z"
  }
}
```

**Validation Rules**:
- `ticket_text`: Required, string, min:10, max:2000

---

### Get Ticket by ID

**Endpoint**: `GET /api/tickets/{id}`

**Response**:
```json
{
  "data": {
    "id": "507f1f77bcf86cd799439011",
    "ticket_text": "...",
    "category": "billing",
    "response": "...",
    "processing_time_ms": 1234,
    "created_at": "2025-11-24T10:30:00Z"
  }
}
```

---

### List All Tickets

**Endpoint**: `GET /api/tickets`

**Response**:
```json
{
  "data": [
    { /* ticket 1 */ },
    { /* ticket 2 */ }
  ],
  "links": { /* pagination */ },
  "meta": { /* pagination meta */ }
}
```

**Pagination**: 20 items per page by default.

---

### Get Statistics

**Endpoint**: `GET /api/statistics`

**Response**:
```json
{
  "data": {
    "total": 150,
    "by_category": {
      "billing": 45,
      "technical": 30,
      "...": "..."
    },
    "avg_processing_time": 1234.56,
    "period_days": 7
  }
}
```

---

## Testing

### Run All Tests

```bash
cd backend
php artisan test
```

**Output**:
```
   PASS  Tests\Unit\OpenAIServiceTest
  ✓ classify returns valid structure
  ✓ classify handles invalid json response
  ...

   PASS  Tests\Feature\ClassifyTicketTest
  ✓ classify endpoint returns successful response
  ...

  Tests:    12 passed (12 assertions)
  Duration: 1.23s
```

---

### Run Accuracy Validation Test

**This test makes REAL OpenAI API calls** to validate ≥80% accuracy on the full dataset.

```bash
RUN_ACCURACY_TEST=true php artisan test --filter AccuracyValidationTest
```

**Expected Output**:
```
=== Testing Accuracy on 50 Examples ===

[1/50] Testing: I was charged twice for this month's membership...
  ✓ Correct: billing

[2/50] Testing: My invoice shows an incorrect amount...
  ✓ Correct: billing

...

=== ACCURACY VALIDATION RESULTS ===

Overall Accuracy: 45/50 (90.0%)
✓ PASSED: Accuracy is ≥80% threshold

Per-Category Accuracy:
  ✓ billing      : 5/5 (100%)
  ✓ technical    : 5/5 (100%)
  ✓ content      : 4/5 (80%)
  ...
```

---

## Project Structure

```
revio-test/
├── python/                          # Fine-tuning scripts
│   ├── prepare_training_data.py     # CSV → JSONL converter
│   ├── fine_tune.py                 # Fine-tuning orchestration
│   ├── validate_model.py            # Accuracy validation
│   ├── data/
│   │   ├── training.jsonl           # Training data (40 examples)
│   │   └── validation.jsonl         # Validation data (10 examples)
│   └── models/
│       └── fine_tuned_model_id.txt  # Saved model ID
│
├── backend/                         # Laravel microservice
│   ├── app/
│   │   ├── Services/
│   │   │   ├── OpenAIService.php            # OpenAI API client
│   │   │   └── TicketClassifierService.php  # Business logic
│   │   ├── Models/Ticket.php
│   │   ├── Http/
│   │   │   ├── Controllers/TicketController.php
│   │   │   ├── Requests/ClassifyTicketRequest.php
│   │   │   └── Resources/TicketResponseResource.php
│   │   └── Exceptions/ClassificationException.php
│   ├── config/
│   │   ├── openai.php               # OpenAI configuration
│   │   └── database.php             # MongoDB configuration
│   ├── routes/api.php
│   └── tests/
│       ├── Unit/                    # Service unit tests
│       └── Feature/                 # API feature tests
│
├── .github/workflows/test.yml       # CI/CD pipeline
├── Support Ticket Category - Support_Tickets_with_Answers.csv
└── README.md
```


