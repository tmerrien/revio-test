<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenAI API key from https://platform.openai.com/api-keys
    |
    */

    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Fine-Tuned Model ID
    |--------------------------------------------------------------------------
    |
    | The ID of your fine-tuned model (e.g., ft:gpt-3.5-turbo-xxx)
    | This is generated after running the fine-tuning process
    |
    */

    'fine_tuned_model' => env('OPENAI_FINE_TUNED_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) to wait for OpenAI API response
    |
    */

    'timeout' => (int) env('OPENAI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Max Retries
    |--------------------------------------------------------------------------
    |
    | Number of times to retry failed requests
    |
    */

    'max_retries' => (int) env('OPENAI_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Temperature
    |--------------------------------------------------------------------------
    |
    | Sampling temperature (0-2). Lower values = more deterministic.
    | For classification tasks, 0.2-0.3 is recommended.
    |
    */

    'temperature' => (float) env('OPENAI_TEMPERATURE', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Max Tokens
    |--------------------------------------------------------------------------
    |
    | Maximum tokens to generate in the response
    |
    */

    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 500),

];
