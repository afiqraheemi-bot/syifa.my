<?php

declare(strict_types=1);

namespace App\Http\RateLimiting;

final readonly class RequestProtectionProfile
{
    /**
     * @param  list<string>  $keyParts
     */
    private function __construct(
        public string $limiter,
        public int $maxAttempts,
        public int $decaySeconds,
        public string $type,
        public string $title,
        public string $detail,
        public array $keyParts,
    ) {}

    /**
     * @param  array<mixed>  $configuration
     */
    public static function fromConfiguration(array $configuration): ?self
    {
        $limiter = $configuration['limiter'] ?? null;
        $maxAttempts = $configuration['max_attempts'] ?? null;
        $decaySeconds = $configuration['decay_seconds'] ?? null;
        $type = $configuration['type'] ?? null;
        $title = $configuration['title'] ?? null;
        $detail = $configuration['detail'] ?? null;
        $keyParts = $configuration['key_parts'] ?? null;

        if (! is_string($limiter) || $limiter === ''
            || ! is_int($maxAttempts) || $maxAttempts < 1
            || ! is_int($decaySeconds) || $decaySeconds < 1
            || ! is_string($type) || $type === ''
            || ! is_string($title) || $title === ''
            || ! is_string($detail) || $detail === ''
            || ! is_array($keyParts)
        ) {
            return null;
        }

        $normalizedKeyParts = [];

        foreach ($keyParts as $keyPart) {
            if (is_string($keyPart) && $keyPart !== '') {
                $normalizedKeyParts[] = $keyPart;
            }
        }

        if ($normalizedKeyParts === []) {
            return null;
        }

        return new self(
            $limiter,
            $maxAttempts,
            $decaySeconds,
            $type,
            $title,
            $detail,
            $normalizedKeyParts,
        );
    }
}
