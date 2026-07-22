<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use DateTimeImmutable;

final class WebsiteSectionCollection
{
    /** @var list<WebsiteSection> */
    private array $sections;

    /** @param list<WebsiteSection> $sections */
    public function __construct(array $sections)
    {
        $this->sections = $sections;
        $this->validate();
        $this->sort();
    }

    /** @param list<SectionId> $identifiers */
    public static function defaults(array $identifiers, DateTimeImmutable $at): self
    {
        $types = SectionType::cases();
        if (count($identifiers) !== count($types)) {
            throw new InvalidWebsiteValueException('Default Website Section identifiers are incomplete.');
        }
        $sections = [];
        foreach ($types as $index => $type) {
            $sections[] = WebsiteSection::create($identifiers[$index], $type, new SectionDisplayOrder($index + 1), $at);
        }

        return new self($sections);
    }

    /** @return list<WebsiteSection> */
    public function sections(): array
    {
        return $this->sections;
    }

    public function enable(SectionId $id, DateTimeImmutable $at): void
    {
        $this->section($id)->setEnabled(true, $at);
    }

    public function disable(SectionId $id, DateTimeImmutable $at): void
    {
        $this->section($id)->setEnabled(false, $at);
    }

    public function reorder(SectionId $id, SectionDisplayOrder $target, DateTimeImmutable $at): void
    {
        if ($target->value > count($this->sections)) {
            throw new InvalidWebsiteValueException('Section display order exceeds the collection boundary.');
        }
        $moving = $this->section($id);
        $old = $moving->displayOrder()->value;
        if ($old === $target->value) {
            return;
        }
        foreach ($this->sections as $section) {
            $order = $section->displayOrder()->value;
            if ($section === $moving) {
                continue;
            }
            if ($old < $target->value && $order > $old && $order <= $target->value) {
                $section->moveTo(new SectionDisplayOrder($order - 1), $at);
            }
            if ($old > $target->value && $order >= $target->value && $order < $old) {
                $section->moveTo(new SectionDisplayOrder($order + 1), $at);
            }
        }
        $moving->moveTo($target, $at);
        $this->sort();
        $this->validate();
    }

    private function section(SectionId $id): WebsiteSection
    {
        foreach ($this->sections as $section) {
            if ($section->id->value === $id->value) {
                return $section;
            }
        }
        throw new InvalidWebsiteValueException('Website Section was not found.');
    }

    private function validate(): void
    {
        if (count($this->sections) !== count(SectionType::cases())) {
            throw new InvalidWebsiteValueException('Website Section Collection must contain every governed type.');
        }
        $types = array_map(static fn (WebsiteSection $section): string => $section->type->value, $this->sections);
        $orders = array_map(static fn (WebsiteSection $section): int => $section->displayOrder()->value, $this->sections);
        if (count(array_unique($types)) !== count($types) || count(array_unique($orders)) !== count($orders)) {
            throw new InvalidWebsiteValueException('Website Section type and display order must be unique.');
        }
        sort($orders);
        if ($orders !== range(1, count($this->sections))) {
            throw new InvalidWebsiteValueException('Website Section ordering must be contiguous.');
        }
    }

    private function sort(): void
    {
        usort($this->sections, static fn (WebsiteSection $left, WebsiteSection $right): int => $left->displayOrder()->value <=> $right->displayOrder()->value);
    }
}
