<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteDraft;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use RuntimeException;

final readonly class ManageWebsiteDraftContentService
{
    public function __construct(
        private WebsiteDraftRepositoryInterface $drafts,
        private WebsiteAuthorization $authorization,
        private WebsiteDraftSectionCodec $codec,
        private ActiveServiceReferenceReadInterface $activeServices,
    ) {}

    public function load(LoadDraftWebsiteContent $command): EditableWebsiteDraftContent
    {
        [$tenant, $website] = $this->scope($command->tenantId, $command->websiteId, $command->authorization);
        $draft = $this->drafts->find($tenant, $website)
            ?? throw new RuntimeException('Website Draft was not found in the authorized scope.');

        return new EditableWebsiteDraftContent($draft, $this->codec);
    }

    public function save(SaveDraftWebsiteContent $command): EditableWebsiteDraftContent
    {
        [$tenant, $website] = $this->scope($command->tenantId, $command->websiteId, $command->authorization);
        $sections = array_map($this->codec->decode(...), $command->sections);
        foreach ($sections as $section) {
            if ($section instanceof ServicesSectionContent) {
                $section->assertReferencesAreEligible(
                    $this->activeServices->forTenant($tenant->value),
                );
            }
        }
        $draft = new WebsiteDraftContent($website, $tenant, $command->expectedVersion, $sections);

        return new EditableWebsiteDraftContent(
            $this->drafts->save($draft, $command->expectedVersion),
            $this->codec,
        );
    }

    /** @return array{TenantId, WebsiteId} */
    private function scope(
        string $tenantId,
        string $websiteId,
        WebsiteAuthorizationContext $authorization,
    ): array {
        $tenant = new TenantId($tenantId);
        $this->authorization->assertCanUpdate($authorization, $tenant);

        return [$tenant, new WebsiteId($websiteId)];
    }
}
