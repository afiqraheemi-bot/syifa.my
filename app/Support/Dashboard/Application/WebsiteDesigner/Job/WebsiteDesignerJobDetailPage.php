<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Modules\Booking\Application\Configuration\ManageBookingFormConfigurationService;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\ClinicOwnerWebsiteApprovalReadInterface;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ManageClinicBookingScheduleService;
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
        private ManageClinicBookingScheduleService $clinicBookingSchedule,
        private ManageWebsiteDraftContentService $websiteDraft,
        private WebsitePublicAddressReadInterface $addresses,
        private WebsiteSubdomainPolicy $subdomains,
        private LaunchReadinessReadInterface $launchReadiness,
        private ClinicOwnerWebsiteApprovalReadInterface $websiteApprovals,
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
        $jobData['clinicName'] = (string) $editableWebsite['branding']['clinic_name'];
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
        $bookingSchedule = $this->clinicBookingSchedule->read(
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
        $readiness = $this->launchReadiness->forJob($jobId);
        $approval = $this->websiteApprovals->forTenant($tenantId);
        $currentApprovalSatisfied = false;
        $readinessConditions = $readiness === null ? [] : $readiness->conditions;
        foreach ($readinessConditions as $condition) {
            if ($condition['key'] === 'approval') {
                $currentApprovalSatisfied = $condition['satisfied'];
                break;
            }
        }
        $approvalCanBeRequested = ! in_array(
            $approval['approvalStatus'] ?? null,
            ['requested', 'resubmitted'],
            true,
        );
        $canSubmitForReview = $approvalCanBeRequested && (
            $editableWebsite['lifecycle'] === 'draft'
            || ($editableWebsite['lifecycle'] === 'ready_for_review' && ! $currentApprovalSatisfied)
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
            'taskUpdateUrlTemplate' => route('dashboard.onboarding.tasks.update', [
                'jobId' => $jobId,
                'taskId' => '__TASK_ID__',
            ]),
            'launchReadiness' => $readiness?->toArray(),
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
                'canSubmitForReview' => $canSubmitForReview,
                'canPublish' => $editableWebsite['lifecycle'] === 'ready_for_review'
                    && $jobData['status'] === 'ready_for_launch'
                    && $readiness?->status === 'ready',
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
                'schedule' => $bookingSchedule->toArray(),
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
                'assetUploadUrl' => route(
                    'website-designer.website-assets.store',
                    (string) $job->data['id'],
                ),
                'assetUrlTemplate' => route(
                    'public-website.assets.show',
                    '__ASSET_ID__',
                ),
            ],
        ]);
    }
}
