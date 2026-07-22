<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

final readonly class FaqEntry implements WebsiteSectionContentItemInterface
{
    public function __construct(public string $id, public string $question, public string $answer)
    {
        SectionContentRules::uuid($id, 'FAQ entry ID');
        SectionContentRules::requiredText($question, 500, 'FAQ question');
        SectionContentRules::requiredText($answer, 5000, 'FAQ answer');
    }

    public function identity(): string
    {
        return $this->id;
    }
}
