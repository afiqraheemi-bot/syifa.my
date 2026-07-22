<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class DoctorsSectionRenderModel implements SectionRenderContract
{
    /** @param list<DoctorRenderModel> $doctors */
    public function __construct(public array $doctors) {}

    public function type(): string
    {
        return 'DOCTORS';
    }
}
