<?php

declare(strict_types=1);

namespace App\Support\Infrastructure;

final readonly class InfrastructureReadinessResult
{
    private function __construct(
        private string $capability,
        private bool $required,
        private bool $ready,
        private ?string $reasonCode,
    ) {}

    public static function ready(string $capability, bool $required): self
    {
        return new self($capability, $required, true, null);
    }

    public static function notReady(string $capability, bool $required, string $reasonCode): self
    {
        return new self($capability, $required, false, $reasonCode);
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    /**
     * @return array{capability: string, status: string, required: bool, reason_code?: string}
     */
    public function toArray(): array
    {
        $result = [
            'capability' => $this->capability,
            'status' => $this->ready ? 'ready' : 'not_ready',
            'required' => $this->required,
        ];

        if ($this->reasonCode !== null) {
            $result['reason_code'] = $this->reasonCode;
        }

        return $result;
    }
}
