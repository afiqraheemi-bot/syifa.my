<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class ServicesSectionRenderModel implements SectionRenderContract
{
    /** @param list<string> $serviceIds */
    public function __construct(public array $serviceIds) {}

    public function type(): string
    {
        return 'SERVICES';
    }
}
