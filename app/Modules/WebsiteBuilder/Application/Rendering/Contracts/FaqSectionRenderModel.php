<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class FaqSectionRenderModel implements SectionRenderContract
{
    /** @param list<FaqEntryRenderModel> $entries */
    public function __construct(public array $entries) {}

    public function type(): string
    {
        return 'FAQ';
    }
}
