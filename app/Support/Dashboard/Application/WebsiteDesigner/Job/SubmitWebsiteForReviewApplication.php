<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Modules\Onboarding\Application\WebsiteApproval\RequestWebsiteApprovalService;
use App\Modules\Onboarding\Contracts\WebsiteApproval\RequestWebsiteApprovalCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\EditableWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReadyForReviewCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReadyForReviewService;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class SubmitWebsiteForReviewApplication
{
    public function __construct(
        private ReadyForReviewService $websites,
        private RequestWebsiteApprovalService $onboarding,
        private ConnectionInterface $connection,
    ) {}

    public function execute(
        WebsiteAuthorizationContext $authorization,
        string $tenantId,
        string $websiteId,
        string $jobId,
        string $approvalId,
        int $expectedWebsiteVersion,
        int $expectedDraftVersion,
        int $expectedJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): EditableWebsiteContent {
        return $this->connection->transaction(function () use (
            $authorization,
            $tenantId,
            $websiteId,
            $jobId,
            $approvalId,
            $expectedWebsiteVersion,
            $expectedDraftVersion,
            $expectedJobVersion,
            $correlationId,
            $occurredAt,
        ): EditableWebsiteContent {
            $website = $this->websites->handle(new ReadyForReviewCommand(
                $authorization,
                $tenantId,
                $websiteId,
                $expectedWebsiteVersion,
                $expectedDraftVersion,
            ));
            $websiteData = $website->toArray();
            $this->onboarding->execute(new RequestWebsiteApprovalCommand(
                $jobId,
                $approvalId,
                $authorization->actorId,
                (int) $websiteData['version'],
                $expectedDraftVersion,
                $expectedJobVersion,
                $correlationId,
                $occurredAt,
            ));

            return $website;
        });
    }
}
