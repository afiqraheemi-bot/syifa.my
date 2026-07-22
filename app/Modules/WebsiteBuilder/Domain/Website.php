<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use DateTimeImmutable;

final class Website
{
    public function __construct(
        public readonly WebsiteId $id,
        public readonly TenantId $tenantId,
        private TemplateId $templateId,
        private WebsiteBranding $branding,
        private WebsiteLifecycle $lifecycle,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private WebsiteSectionCollection $sections,
        private int $version = 0,
    ) {
        if ($version < 0 || $updatedAt < $createdAt) {
            throw new InvalidWebsiteValueException('Website persisted state is invalid.');
        }
    }

    /** @param list<SectionId> $sectionIds */
    public static function create(WebsiteId $id, TenantId $tenantId, TemplateId $templateId, WebsiteBranding $branding, array $sectionIds, DateTimeImmutable $at): self
    {
        return new self($id, $tenantId, $templateId, $branding, WebsiteLifecycle::Draft, $at, $at, WebsiteSectionCollection::defaults($sectionIds, $at));
    }

    public function templateId(): TemplateId
    {
        return $this->templateId;
    }

    public function branding(): WebsiteBranding
    {
        return $this->branding;
    }

    public function lifecycle(): WebsiteLifecycle
    {
        return $this->lifecycle;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function sections(): WebsiteSectionCollection
    {
        return $this->sections;
    }

    public function enableSection(SectionId $id, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->sections->enable($id, $at);
        $this->updatedAt = $at;
    }

    public function disableSection(SectionId $id, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->sections->disable($id, $at);
        $this->updatedAt = $at;
    }

    public function reorderSection(SectionId $id, SectionDisplayOrder $order, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->sections->reorder($id, $order, $at);
        $this->updatedAt = $at;
    }

    public function updateBranding(WebsiteBranding $branding, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->branding = $branding;
        $this->updatedAt = $at;
    }

    public function selectTemplate(TemplateId $templateId, DateTimeImmutable $at): void
    {
        if (in_array($this->lifecycle, [WebsiteLifecycle::Published, WebsiteLifecycle::Archived], true)) {
            throw new InvalidWebsiteValueException('Website template cannot change after publication.');
        }
        $this->templateId = $templateId;
        $this->updatedAt = $at;
    }

    public function readyForReview(DateTimeImmutable $at): void
    {
        $this->transition(WebsiteLifecycle::Draft, WebsiteLifecycle::ReadyForReview, $at);
    }

    public function publish(WebsitePublicationEvidence $evidence, DateTimeImmutable $at): void
    {
        $this->transition(WebsiteLifecycle::ReadyForReview, WebsiteLifecycle::Published, $at);
    }

    public function archive(DateTimeImmutable $at): void
    {
        $this->transition(WebsiteLifecycle::Published, WebsiteLifecycle::Archived, $at);
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidWebsiteValueException('Persisted Website version must be positive.');
        }
        $this->version = $version;
    }

    private function transition(WebsiteLifecycle $from, WebsiteLifecycle $to, DateTimeImmutable $at): void
    {
        if ($this->lifecycle !== $from) {
            throw new InvalidWebsiteValueException(sprintf('Website cannot transition from %s to %s.', $this->lifecycle->value, $to->value));
        }
        $this->lifecycle = $to;
        $this->updatedAt = $at;
    }

    private function assertNotArchived(): void
    {
        if ($this->lifecycle === WebsiteLifecycle::Archived) {
            throw new InvalidWebsiteValueException('Archived Website cannot be changed.');
        }
    }
}
