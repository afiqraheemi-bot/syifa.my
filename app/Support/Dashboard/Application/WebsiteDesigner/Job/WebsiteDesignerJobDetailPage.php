<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Modules\Booking\Application\Configuration\ManageBookingFormConfigurationService;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileService;
use App\Modules\WebsiteBuilder\Application\WebsiteAddress\WebsiteSubdomainPolicy;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\LoadDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class WebsiteDesignerJobDetailPage
{
    public function __construct(
        private WebsiteDesignerJobDetailProvider $detail,
        private ManageWebsiteContentService $websiteContent,
        private ManageBookingFormConfigurationService $bookingConfiguration,
        private UpdateClinicContactProfileService $clinicContact,
        private ManageWebsiteDraftContentService $websiteDraft,
        private WebsitePublicAddressReadInterface $addresses,
        private WebsiteSubdomainPolicy $subdomains,
    ) {}

    public function fromTrustedContext(mixed $context, string $jobId): ?DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Website Designer dashboard context was not established.');
        }
        $job = $this->detail->provide($context, $jobId);
        if ($job === null) {
            return null;
        }
        $tenantId = (string) $job->data['tenantId'];
        $editableWebsite = $this->websiteContent->read(
            $tenantId,
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                assignedTenantId: $tenantId,
            ),
        )->toArray();
        $jobData = $job->data;
        if ($editableWebsite['lifecycle'] === 'ready_for_review') {
            $jobData['timeline'][] = [
                'key' => 'website_ready_for_review',
                'title' => 'Website ready for review',
                'occurredAt' => $editableWebsite['updated_at'],
                'occurredAtLabel' => (new \DateTimeImmutable(
                    (string) $editableWebsite['updated_at'],
                ))->format('j M Y, g:i A'),
            ];
        }
        if ($editableWebsite['lifecycle'] === 'published') {
            $jobData['timeline'][] = [
                'key' => 'website_published',
                'title' => 'Website published',
                'occurredAt' => $editableWebsite['updated_at'],
                'occurredAtLabel' => (new \DateTimeImmutable(
                    (string) $editableWebsite['updated_at'],
                ))->format('j M Y, g:i A'),
            ];
        }
        $editableBooking = $this->bookingConfiguration->read($tenantId)->toArray();
        $contactProfile = $this->clinicContact->read(
            $tenantId,
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                assignedTenantId: $tenantId,
            ),
        );
        $editableDraft = $this->websiteDraft->load(new LoadDraftWebsiteContent(
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                assignedTenantId: $tenantId,
            ),
            $tenantId,
            (string) $job->data['websiteId'],
        ))->toArray();
        $address = $this->addresses->forWebsite(
            $tenantId,
            (string) $job->data['websiteId'],
        );

        return new DashboardPageView('PlatformAdministration/Onboarding/WebsiteDesignerJobDetail', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('onboarding', 'Onboarding', route('dashboard.onboarding'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'onboarding', 'label' => 'Onboarding', 'href' => route('dashboard.onboarding')],
                ['key' => 'job', 'label' => 'Assigned job'],
            ],
            'pageTitle' => 'Assigned job detail',
            'pageDescription' => 'Review onboarding progress and operational readiness.',
            'identityName' => $context->name,
            'contextLabel' => 'Website Designer workspace',
            'job' => $jobData,
            'websiteSetup' => [
                'configuration' => $editableWebsite,
                'templateOptions' => array_map(static fn (TemplateId $template): array => [
                    'value' => $template->value,
                    'label' => match ($template) {
                        TemplateId::SyifaEssential => 'Syifa Essential',
                        TemplateId::SyifaCare => 'Syifa Care',
                        TemplateId::SyifaDental => 'Syifa Dental',
                        TemplateId::SyifaAesthetic => 'Syifa Aesthetic',
                        TemplateId::SyifaSpecialist => 'Syifa Specialist',
                    },
                ], TemplateId::cases()),
                'updateUrl' => route('dashboard.onboarding.show', (string) $job->data['id']),
                'readyForReviewUrl' => route(
                    'dashboard.onboarding.show',
                    (string) $job->data['id'],
                ),
                'previewUrl' => route(
                    'dashboard.onboarding.preview',
                    (string) $job->data['id'],
                ),
                'publishUrl' => route(
                    'dashboard.onboarding.publish',
                    (string) $job->data['id'],
                ),
                'canSubmitForReview' => $editableWebsite['lifecycle'] === 'draft',
                'canPublish' => $editableWebsite['lifecycle'] === 'ready_for_review',
                'address' => $address === null ? null : [
                    'host' => $address->host,
                    'url' => $address->url,
                    'status' => $address->status(),
                    'active' => $address->active,
                ],
                'addressUrl' => route(
                    'dashboard.onboarding.website-address',
                    (string) $job->data['id'],
                ),
                'canReserveAddress' => $editableWebsite['lifecycle'] !== 'published',
                'baseDomain' => $this->subdomains->baseDomain(),
            ],
            'bookingSetup' => [
                'configuration' => $editableBooking,
                'updateUrl' => route('dashboard.onboarding.show', (string) $job->data['id']),
            ],
            'clinicContact' => [
                'configuration' => $contactProfile->toArray(),
                'updateUrl' => route('dashboard.onboarding.show', (string) $job->data['id']),
            ],
            'websiteDraft' => [
                'draft' => $editableDraft,
                'updateUrl' => route(
                    'website-designer.website-draft.update',
                    (string) $job->data['id'],
                ),
            ],
        ]);
    }
}
