<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('SYIFA_AI_ENABLED', false),
    'provider' => env('SYIFA_AI_PROVIDER', 'openai'),
    'model' => env('SYIFA_AI_MODEL', 'gpt-5.6-luna'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'api_key' => env('OPENAI_API_KEY'),
    'timeout_seconds' => (int) env('SYIFA_AI_TIMEOUT_SECONDS', 30),
    'max_output_tokens' => (int) env('SYIFA_AI_MAX_OUTPUT_TOKENS', 1400),
    'monthly_tenant_token_limit' => (int) env('SYIFA_AI_MONTHLY_TENANT_TOKEN_LIMIT', 250000),
];
