<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final readonly class ServicesSectionContent implements WebsiteSectionContentInterface
{
    /** @var list<ServicePresentationItem> */
    public array $items;

    /** @param list<ServicePresentationItem|string> $items */
    public function __construct(private SectionId $sectionId, array $items = [])
    {
        $normalized = array_map(
            static fn (ServicePresentationItem|string $item, int $index): ServicePresentationItem => is_string($item)
                ? new ServicePresentationItem($item, $index + 1)
                : $item,
            $items,
            array_keys($items),
        );
        SectionContentRules::uniqueItemIds($normalized, 'Service presentation items');
        $orders = array_map(static fn (ServicePresentationItem $item): int => $item->displayOrder, $normalized);
        if (count(array_unique($orders)) !== count($orders)) {
            throw new InvalidWebsiteValueException('Service presentation display orders must be unique.');
        }
        if (count(array_filter($normalized, static fn (ServicePresentationItem $item): bool => $item->isFeatured)) > 1) {
            throw new InvalidWebsiteValueException('At most one Service may be featured in a Services Section.');
        }
        usort($normalized, static fn (ServicePresentationItem $left, ServicePresentationItem $right): int => $left->displayOrder <=> $right->displayOrder);
        $this->items = $normalized;
    }

    /** @param list<string> $activeServiceReferences */
    public function isRenderable(array $activeServiceReferences): bool
    {
        SectionContentRules::uniqueUuids($activeServiceReferences, 'Active Service references');

        return array_intersect($this->serviceReferences(), $activeServiceReferences) !== [];
    }

    public function sectionId(): SectionId
    {
        return $this->sectionId;
    }

    public function sectionType(): SectionType
    {
        return SectionType::Services;
    }

    /** @return list<string> */
    public function serviceReferences(): array
    {
        return array_map(static fn (ServicePresentationItem $item): string => $item->serviceId, $this->items);
    }

    public function withFeaturedService(?string $serviceId): self
    {
        if ($serviceId !== null && ! in_array($serviceId, $this->serviceReferences(), true)) {
            throw new InvalidWebsiteValueException('Featured Service must be present in the Services Section.');
        }

        return new self($this->sectionId, array_map(
            static fn (ServicePresentationItem $item): ServicePresentationItem => new ServicePresentationItem(
                $item->serviceId,
                $item->displayOrder,
                $item->serviceId === $serviceId,
            ),
            $this->items,
        ));
    }

    public function equals(self $other): bool
    {
        return $this->sectionId->value === $other->sectionId->value && $this->items == $other->items;
    }
}
