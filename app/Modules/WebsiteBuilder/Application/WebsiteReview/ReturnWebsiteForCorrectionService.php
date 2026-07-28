<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteReview;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;
use RuntimeException;

final readonly class ReturnWebsiteForCorrectionService
{
    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsiteAuthorization $authorization,
    ) {}

    public function execute(ReturnWebsiteForCorrectionCommand $command): void
    {
        $tenantId = new TenantId($command->tenantId);
        $websiteId = new WebsiteId($command->websiteId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);
        $website = $this->websites->findById($tenantId, $websiteId)
            ?? throw new RuntimeException('Website was not found in the authorized scope.');
        if ($website->version() !== $command->expectedVersion) {
            throw new StaleWebsiteWriteException(
                'Website changed since the Clinic Owner reviewed it.',
            );
        }
        $at = new DateTimeImmutable;
        if ($at < $website->updatedAt()) {
            $at = $website->updatedAt()->modify('+1 microsecond');
        }
        $website->returnToDraftForCorrection($at);
        $this->websites->save($website);
    }
}
