<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePublication;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\WebsitePublicationReadinessEvaluator;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Publication\WebsitePublicationApprovalReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\WebsitePublicationTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use DateTimeImmutable;
use RuntimeException;

final readonly class PublishWebsiteService
{
    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsiteDraftRepositoryInterface $drafts,
        private ClinicRepositoryInterface $clinics,
        private PublicBookingFormConfigurationReaderInterface $booking,
        private SubscriptionSummaryReadInterface $subscriptions,
        private WebsitePublicAddressRepositoryInterface $addresses,
        private WebsitePublicationTransactionInterface $transaction,
        private WebsiteAuthorization $authorization,
        private WebsitePublicationReadinessEvaluator $readiness,
        private WebsitePublicationContentFactory $content,
        private WebsitePublicationApprovalReadInterface $approval,
    ) {}

    public function handle(PublishWebsiteCommand $command): PublishedWebsiteResult
    {
        if ($command->authorization->role !== 'website_designer') {
            throw new WebsiteOperationForbiddenException(
                'Only an assigned Website Designer may publish a Website.',
            );
        }

        $tenantId = new TenantId($command->tenantId);
        $websiteId = new WebsiteId($command->websiteId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);

        return $this->transaction->run(
            $tenantId->value,
            $websiteId->value,
            function () use ($command, $tenantId, $websiteId): PublishedWebsiteResult {
                $website = $this->websites->findById($tenantId, $websiteId)
                    ?? throw new RuntimeException('Website was not found in the authorized scope.');
                $draft = $this->drafts->find($tenantId, $websiteId)
                    ?? throw new RuntimeException('Website Draft was not found in the authorized scope.');
                if ($website->version() !== $command->expectedWebsiteVersion
                    || $draft->version !== $command->expectedDraftVersion) {
                    throw new StaleWebsiteWriteException(
                        'Website or Draft changed since publication was reviewed.',
                    );
                }
                if (! $this->approval->isApproved(
                    $tenantId->value,
                    $websiteId->value,
                    $website->version(),
                    $draft->version,
                )) {
                    throw new InvalidWebsiteValueException(
                        'Initial publication requires current Clinic Owner approval and Onboarding launch readiness.',
                    );
                }

                $clinic = $this->clinics->findByTenantId($tenantId)
                    ?? throw new RuntimeException('Clinic was not found in the authorized scope.');
                $booking = $this->booking->forTrustedTenant($tenantId->value);
                $activeServices = array_map(
                    static fn ($service): string => $service->id,
                    $booking->services,
                );
                $readiness = $this->readiness->evaluate(
                    $website,
                    $draft,
                    $activeServices,
                );
                $subscription = $this->subscriptions->summary($tenantId->value);
                $now = new DateTimeImmutable;
                $entitlementActive = $subscription !== null
                    && $subscription->status === 'active'
                    && $subscription->endsOn >= $now->format('Y-m-d');
                if (! $entitlementActive) {
                    throw new InvalidWebsiteValueException(
                        'Website publication requires an active Subscription entitlement.',
                    );
                }

                $publishedAt = $now < $website->updatedAt()
                    ? $website->updatedAt()->modify('+1 microsecond')
                    : $now;
                $website->publish(
                    new WebsitePublicationEvidence(true, true),
                    $readiness,
                    $this->content->create($website, $draft, $clinic, $booking),
                    new PublicationId($command->publicationId),
                    $command->authorization->actorId,
                    $publishedAt,
                );
                $this->websites->save($website);
                $this->addresses->activatePrimary(
                    $tenantId->value,
                    $websiteId->value,
                    $publishedAt,
                );

                return new PublishedWebsiteResult(
                    $website->id->value,
                    $website->version(),
                    $website->publishedVersion(),
                    $website->lifecycle()->value,
                    $command->publicationId,
                    $publishedAt->format(DATE_ATOM),
                );
            },
        );
    }
}
