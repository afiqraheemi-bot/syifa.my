<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\SyifaAi;

use InvalidArgumentException;

final readonly class SyifaAiGenerationResult
{
    /**
     * @param  list<array{field: string, label: string, proposed_value: string, rationale: string}>  $suggestions
     * @param  list<array{label: string, status: string, message: string}>  $checks
     * @param  list<string>  $nextActions
     */
    public function __construct(
        public string $title,
        public string $summary,
        public array $suggestions,
        public array $checks,
        public array $nextActions,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {
        if ($title === '' || $summary === '' || $inputTokens < 0 || $outputTokens < 0) {
            throw new InvalidArgumentException('SYIFA AI result is invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'suggestions' => $this->suggestions,
            'checks' => $this->checks,
            'next_actions' => $this->nextActions,
        ];
    }
}
