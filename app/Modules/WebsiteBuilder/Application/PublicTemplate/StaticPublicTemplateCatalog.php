<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\PublicTemplate;

use App\Modules\WebsiteBuilder\Contracts\PublicTemplate\PublicTemplateCatalogInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicTemplate\PublicTemplateOption;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;

final class StaticPublicTemplateCatalog implements PublicTemplateCatalogInterface
{
    public function options(): array
    {
        return array_map(static fn (TemplateId $template): PublicTemplateOption => new PublicTemplateOption(
            $template->value,
            match ($template) {
                TemplateId::SyifaEssential => 'Syifa Essential',
                TemplateId::SyifaCare => 'Syifa Care',
                TemplateId::SyifaDental => 'Syifa Dental',
                TemplateId::SyifaAesthetic => 'Syifa Aesthetic',
                TemplateId::SyifaSpecialist => 'Syifa Specialist',
            },
        ), TemplateId::cases());
    }
}
