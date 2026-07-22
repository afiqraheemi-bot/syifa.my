<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class ServicesSectionRenderModel implements SectionRenderContract
{
    /** @param list<ServiceItemRenderModel> $services */
    public function __construct(public array $services) {}

    public function type(): string
    {
        return 'SERVICES';
    }
}
