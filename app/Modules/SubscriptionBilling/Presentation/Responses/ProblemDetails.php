<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Responses;

final class ProblemDetails extends BaseApiResponse
{
    /**
     * @param  array<string, list<string>>|null  $validationErrors
     */
    public function __construct(
        string $correlationId,
        public readonly string $type,
        public readonly string $title,
        public readonly int $status,
        public readonly string $detail,
        public readonly ?string $instance = null,
        public readonly ?array $validationErrors = null,
    ) {
        parent::__construct($correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'correlation_id' => $this->correlationId,
        ];

        if ($this->instance !== null) {
            $payload['instance'] = $this->instance;
        }

        if ($this->validationErrors !== null) {
            $payload['errors'] = $this->validationErrors;
        }

        return $payload;
    }
}
