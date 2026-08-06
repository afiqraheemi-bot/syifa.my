<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\SyifaAi;

final readonly class SyifaAiGenerationRequest
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public SyifaAiCapability $capability,
        public ?SyifaAiSection $section,
        public ?string $instruction,
        public array $context,
        public string $safetyIdentifier,
    ) {}
}
