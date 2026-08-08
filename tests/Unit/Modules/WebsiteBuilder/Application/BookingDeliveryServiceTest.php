<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\AvailabilityDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingDraft;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingSuccessData;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAvailabilityCacheInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingServiceOption;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmissionResult;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryPublicAvailabilityCache;

final class BookingDeliveryServiceTest extends TestCase
{
    public function test_availability_for_date_resolves_through_the_availability_delivery_service(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');

        $expected = [new PublicAvailabilitySlot('2026-08-03', '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Available)];
        $reader = $this->createStub(PublicAvailabilityReaderInterface::class);
        $reader->method('forDate')->willReturn($expected);
        $availability = new AvailabilityDeliveryService($tenants, $reader, $this->cache());

        $result = $this->service($tenants, $availability)->availabilityForDate('website-1', '2026-08-03');

        self::assertSame($expected, $result);
    }

    public function test_form_configuration_for_reflects_service_selection_state(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $formConfiguration = $this->service($tenants, $availability, serviceSelectionEnabled: true)->formConfigurationFor('website-1');

        self::assertTrue($formConfiguration->serviceSelectionEnabled);
    }

    public function test_submit_assembles_the_submission_from_the_draft_and_the_resolved_tenant(): void
    {
        $tenants = $this->createMock(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->with('website-1')->willReturn('tenant-1');

        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $submissions = $this->createMock(BookingSubmissionGatewayInterface::class);
        $submissions->expects(self::once())->method('submit')->with(self::callback(
            static fn (PublicBookingSubmission $submission): bool => $submission->tenantId === 'tenant-1'
                && $submission->patientName === 'Aisyah'
                && $submission->phone === '+60123456789'
                && $submission->appointmentDate === '2026-08-03'
                && $submission->appointmentTime === '09:00'
                && $submission->consent === true,
        ))->willReturn(new PublicBookingSubmissionResult('BOOK-STUB-ABC', 'submitted', new DateTimeImmutable('2026-08-03T01:00:00Z')));

        $draft = (new BookingDraft)
            ->withDate('2026-08-03')
            ->withTime('09:00')
            ->withPatientDetails('Aisyah', '+60123456789', null, null, true);

        $success = $this->service($tenants, $availability, $submissions)->submit('website-1', $draft);

        self::assertSame('BOOK-STUB-ABC', $success->reference);
        self::assertSame('submitted', $success->status);
    }

    public function test_submit_lets_an_availability_exception_propagate_unhandled(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());
        $submissions = $this->createStub(BookingSubmissionGatewayInterface::class);
        $submissions->method('submit')->willThrowException(new PublicBookingAvailabilityException('taken'));

        $this->expectException(PublicBookingAvailabilityException::class);

        $draft = (new BookingDraft)->withDate('2026-08-03')->withTime('09:00')->withPatientDetails('Aisyah', '+60123456789', null, null, true);
        $this->service($tenants, $availability, $submissions)->submit('website-1', $draft);
    }

    public function test_service_selection_view_model_is_null_when_disabled(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $service = $this->service($tenants, $availability, serviceSelectionEnabled: false);

        self::assertNull($service->serviceSelectionViewModel($this->context(), new BookingDraft));
    }

    public function test_service_selection_view_model_always_offers_a_not_sure_option_when_not_required(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $viewModel = $this->service($tenants, $availability, serviceSelectionEnabled: true)
            ->serviceSelectionViewModel($this->context(), new BookingDraft);

        self::assertNotNull($viewModel);
        $notSure = array_filter($viewModel->options, static fn ($option): bool => $option->id === '');
        self::assertNotEmpty($notSure);
    }

    public function test_date_selection_aggregates_available_when_any_slot_that_day_is_available(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $reader = $this->createStub(PublicAvailabilityReaderInterface::class);
        $reader->method('forDate')->willReturn([
            new PublicAvailabilitySlot('2026-08-03', '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Unavailable),
            new PublicAvailabilitySlot('2026-08-03', '09:30', '10:00', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Available),
        ]);
        $availability = new AvailabilityDeliveryService($tenants, $reader, $this->cache());

        $viewModel = $this->service($tenants, $availability)->dateSelectionViewModel($this->context(), new BookingDraft);

        self::assertTrue($viewModel->hasAnyAvailableDate());
    }

    public function test_time_selection_is_null_when_no_date_is_selected_yet(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $viewModel = $this->service($tenants, $availability)->timeSelectionViewModel($this->context(), new BookingDraft);

        self::assertNull($viewModel);
    }

    public function test_patient_details_view_model_is_built_by_the_delivery_service(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $viewModel = $this->service($tenants, $availability)->patientDetailsViewModel(
            $this->context(),
            'Aisyah',
            '+60123456789',
            'aisyah@example.test',
            'First visit',
            true,
        );

        self::assertSame('Aisyah', $viewModel->patientName);
        self::assertTrue($viewModel->consent);
        self::assertTrue($viewModel->emailEnabled);
        self::assertTrue($viewModel->notesEnabled);
    }

    public function test_review_view_model_is_built_only_for_a_complete_draft(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());
        $service = $this->service($tenants, $availability);

        self::assertNull($service->reviewViewModel($this->context(), new BookingDraft));

        $draft = (new BookingDraft)
            ->withService('service-1')
            ->withDate('2026-08-03')
            ->withTime('09:00')
            ->withPatientDetails('Aisyah', '+60123456789', null, null, true);
        $viewModel = $service->reviewViewModel($this->context(), $draft);

        self::assertNotNull($viewModel);
        self::assertSame('General Consultation', $viewModel->serviceLabel);
        self::assertSame('Aisyah', $viewModel->patientName);
    }

    public function test_success_view_model_normalizes_submitted_status_inside_the_delivery_service(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        $viewModel = $this->service($tenants, $availability)->successViewModel(
            $this->context(),
            new BookingSuccessData(
                'BOOK-ABC123',
                'submitted',
                new DateTimeImmutable('2026-08-03T01:00:00Z'),
            ),
        );

        self::assertSame('BOOK-ABC123', $viewModel->reference);
        self::assertSame('received', $viewModel->statusLabel);
        self::assertSame('3 Aug 2026, 1:00 AM', $viewModel->submittedAtLabel);
    }

    public function test_total_steps_accounts_for_whether_service_selection_is_enabled(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');
        $availability = new AvailabilityDeliveryService($tenants, $this->createStub(PublicAvailabilityReaderInterface::class), $this->cache());

        self::assertSame(4, $this->service($tenants, $availability, serviceSelectionEnabled: true)->totalSteps(new PublicBookingFormConfiguration(true, false, true, true, [])));
        self::assertSame(3, $this->service($tenants, $availability, serviceSelectionEnabled: false)->totalSteps(new PublicBookingFormConfiguration(false, false, true, true, [])));
    }

    private function service(
        WebsiteTenantResolverInterface $tenants,
        AvailabilityDeliveryService $availability,
        ?BookingSubmissionGatewayInterface $submissions = null,
        bool $serviceSelectionEnabled = true,
    ): BookingDeliveryService {
        $formConfigurations = $this->createStub(PublicBookingFormConfigurationReaderInterface::class);
        $formConfigurations->method('forTrustedTenant')->willReturn(new PublicBookingFormConfiguration(
            $serviceSelectionEnabled,
            false,
            true,
            true,
            [new PublicBookingServiceOption('service-1', 'General Consultation', true)],
        ));
        $websites = $this->createStub(PublicWebsiteRenderModelProviderInterface::class);
        $websites->method('find')->willReturn(null);

        return new BookingDeliveryService(
            $tenants,
            $availability,
            $submissions ?? $this->createStub(BookingSubmissionGatewayInterface::class),
            $formConfigurations,
            $websites,
        );
    }

    private function context(): PublicSiteContext
    {
        return new PublicSiteContext('https', 'clinic.example.com', '', '00000000-0000-4000-8000-000000000001');
    }

    private function cache(): PublicAvailabilityCacheInterface
    {
        return new InMemoryPublicAvailabilityCache;
    }
}
