<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteContent;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LogoDisplaySize;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WhatsAppButtonStyle;
use DateTimeImmutable;
use RuntimeException;

final readonly class ManageWebsiteContentService
{
    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsiteAuthorization $authorization,
        private WebsiteTemplateAvailabilityPolicy $templateAvailability,
    ) {}

    public function read(string $tenantId, WebsiteAuthorizationContext $authorization): EditableWebsiteContent
    {
        $tenant = new TenantId($tenantId);
        $this->authorization->assertCanUpdate($authorization, $tenant);
        $website = $this->websites->findByTenant($tenant)
            ?? throw new RuntimeException('Website configuration was not found.');

        return new EditableWebsiteContent($website);
    }

    public function update(UpdateWebsiteContentCommand $command): EditableWebsiteContent
    {
        $tenant = new TenantId($command->tenantId);
        $this->authorization->assertCanUpdate($command->authorization, $tenant);
        $website = $this->websites->findByTenant($tenant)
            ?? throw new RuntimeException('Website configuration was not found.');
        if ($website->version() !== $command->expectedVersion) {
            throw new StaleWebsiteWriteException('Website content changed since it was loaded.');
        }

        $at = new DateTimeImmutable;
        if ($at < $website->updatedAt()) {
            $at = $website->updatedAt()->modify('+1 microsecond');
        }

        if ($command->templateId !== null) {
            $templateId = TemplateId::from($command->templateId);
            if ($website->templateId() !== $templateId) {
                WebsiteTemplateChangePolicy::assertPermitted(
                    $command->authorization->role,
                    $website->publishedVersion(),
                    true,
                );
                if (! $this->templateAvailability->isAvailable($tenant->value, $templateId, $at)) {
                    throw new InvalidWebsiteValueException(
                        'This template is not included in the current Subscription plan.',
                    );
                }
                $website->selectTemplate($templateId, $at);
            }
        }

        $currentBranding = $website->branding();
        $website->updateBranding(new WebsiteBranding(
            $command->clinicName,
            $command->tagline,
            $command->primaryColor,
            $command->secondaryColor,
            $command->logoReference === null ? null : new AssetId($command->logoReference),
            $currentBranding->faviconReference,
            $command->contactEmail,
            $command->contactPhone,
            $command->address,
            $command->socialLinks,
            LogoDisplaySize::fromStored($command->logoDisplaySize),
            WhatsAppButtonStyle::fromStored($command->whatsAppButtonStyle),
        ), $at);

        $website->configureSeo(
            $command->metaTitle,
            $command->metaDescription,
            $command->metaKeywords,
            $command->canonicalUrl,
            RobotsDirective::from($command->robotsDirective),
            $command->openGraphTitle,
            $command->openGraphDescription,
            $command->openGraphImageReference === null ? null : new AssetId($command->openGraphImageReference),
            $command->indexingEnabled,
            $at,
        );

        foreach ($website->sections()->sections() as $section) {
            $enabled = $command->sections[strtolower($section->type->value)] ?? $section->enabled();
            $enabled
                ? $website->enableSection($section->id, $at)
                : $website->disableSection($section->id, $at);
        }

        $this->websites->save($website);

        return new EditableWebsiteContent($website);
    }
}
