<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

final readonly class ManualDoctorProfile implements WebsiteSectionContentItemInterface
{
    public function __construct(public string $id, public string $name, public ?string $professionalTitle = null, public bool $visible = true)
    {
        SectionContentRules::uuid($id, 'Manual doctor profile ID');
        SectionContentRules::requiredText($name, 160, 'Manual doctor name');
        SectionContentRules::optionalText($professionalTitle, 160, 'Manual doctor professional title');
    }

    public function identity(): string
    {
        return $this->id;
    }
}
