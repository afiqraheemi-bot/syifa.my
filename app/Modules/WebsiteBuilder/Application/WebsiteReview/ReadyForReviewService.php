<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteReview;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\EditableWebsiteContent;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;
use RuntimeException;

final readonly class ReadyForReviewService
{
    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsiteDraftRepositoryInterface $drafts,
        private ActiveServiceReferenceReadInterface $activeServices,
        private WebsiteAuthorization $authorization,
        private WebsitePublicationReadinessEvaluator $readiness,
    ) {}

    public function handle(ReadyForReviewCommand $command): EditableWebsiteContent
    {
        $tenantId = new TenantId($command->tenantId);
        $websiteId = new WebsiteId($command->websiteId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);

        $website = $this->websites->findById($tenantId, $websiteId)
            ?? throw new RuntimeException('Website was not found in the authorized scope.');
        if ($website->version() !== $command->expectedVersion) {
            throw new StaleWebsiteWriteException(
                'Website changed since readiness was reviewed.',
            );
        }
        $draft = $this->drafts->find($tenantId, $websiteId)
            ?? throw new RuntimeException('Website Draft was not found in the authorized scope.');

        $this->readiness->evaluate(
            $website,
            $draft,
            $this->activeServices->forTenant($tenantId->value),
        );

        $at = new DateTimeImmutable;
        if ($at < $website->updatedAt()) {
            $at = $website->updatedAt()->modify('+1 microsecond');
        }
        $website->readyForReview($at);
        $this->websites->save($website);

        return new EditableWebsiteContent($website);
    }
}
