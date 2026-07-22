<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

final readonly class WebsiteSummaryData
{
    public function __construct(public string $id, public string $tenantId, public string $clinicName, public string $templateId, public string $lifecycle) {}
}
