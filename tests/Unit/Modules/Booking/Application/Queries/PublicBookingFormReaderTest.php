<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Application\Queries;

use App\Modules\Booking\Application\Queries\PublicBookingFormReader;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicBookingFormReaderTest extends TestCase
{
    public function test_it_projects_only_the_four_permitted_booleans_and_active_services(): void
    {
        $tenantId = new TenantId('00000000-0000-4000-8000-000000000001');
        $configuration = $this->configuration($tenantId, serviceSelection: true, email: true, notes: false);

        $formConfigurations = $this->createStub(BookingFormConfigurationRepositoryInterface::class);
        $formConfigurations->method('findByTenant')->willReturn($configuration);

        $service = Service::register(new ServiceId('00000000-0000-4000-8000-000000000002'), $tenantId, new ServiceName('General Consultation'), null, new SortOrder(0), new DateTimeImmutable);
        $services = $this->createStub(ServiceRepositoryInterface::class);
        $services->method('findActive')->willReturn([$service]);

        $data = (new PublicBookingFormReader($formConfigurations, $services))->forTrustedTenant($tenantId->value);

        self::assertTrue($data->serviceSelectionEnabled);
        self::assertFalse($data->serviceSelectionRequired);
        self::assertTrue($data->emailEnabled);
        self::assertFalse($data->notesEnabled);
        self::assertCount(1, $data->services);
        self::assertSame('General Consultation', $data->services[0]->name);
        self::assertFalse(property_exists($data, 'doctorSelectionEnabled'));
        self::assertFalse(property_exists($data, 'branchEnabled'));
    }

    public function test_service_selection_required_reflects_required_fields(): void
    {
        $tenantId = new TenantId('00000000-0000-4000-8000-000000000001');
        $configuration = $this->configuration($tenantId, serviceSelection: true, email: false, notes: false, serviceRequired: true);
        $formConfigurations = $this->createStub(BookingFormConfigurationRepositoryInterface::class);
        $formConfigurations->method('findByTenant')->willReturn($configuration);
        $services = $this->createStub(ServiceRepositoryInterface::class);
        $services->method('findActive')->willReturn([]);

        $data = (new PublicBookingFormReader($formConfigurations, $services))->forTrustedTenant($tenantId->value);

        self::assertTrue($data->serviceSelectionRequired);
    }

    public function test_no_configuration_yet_fails_closed_to_the_most_conservative_projection(): void
    {
        $formConfigurations = $this->createStub(BookingFormConfigurationRepositoryInterface::class);
        $formConfigurations->method('findByTenant')->willReturn(null);
        $services = $this->createStub(ServiceRepositoryInterface::class);

        $data = (new PublicBookingFormReader($formConfigurations, $services))->forTrustedTenant('00000000-0000-4000-8000-000000000001');

        self::assertFalse($data->serviceSelectionEnabled);
        self::assertFalse($data->serviceSelectionRequired);
        self::assertFalse($data->emailEnabled);
        self::assertFalse($data->notesEnabled);
        self::assertSame([], $data->services);
    }

    public function test_services_are_never_read_when_service_selection_is_disabled(): void
    {
        $tenantId = new TenantId('00000000-0000-4000-8000-000000000001');
        $configuration = $this->configuration($tenantId, serviceSelection: false, email: true, notes: true);
        $formConfigurations = $this->createStub(BookingFormConfigurationRepositoryInterface::class);
        $formConfigurations->method('findByTenant')->willReturn($configuration);
        $services = $this->createMock(ServiceRepositoryInterface::class);
        $services->expects(self::never())->method('findActive');

        $data = (new PublicBookingFormReader($formConfigurations, $services))->forTrustedTenant($tenantId->value);

        self::assertSame([], $data->services);
    }

    private function configuration(TenantId $tenantId, bool $serviceSelection, bool $email, bool $notes, bool $serviceRequired = false): BookingFormConfiguration
    {
        $order = [BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime];
        if ($serviceSelection) {
            $order[] = BookingFormField::Service;
        }
        if ($email) {
            $order[] = BookingFormField::Email;
        }
        if ($notes) {
            $order[] = BookingFormField::Notes;
        }

        return BookingFormConfiguration::create(
            $tenantId,
            enableServiceSelection: $serviceSelection,
            enableDoctorSelection: false,
            enableEmail: $email,
            enableBranch: false,
            enableNotes: $notes,
            requiredFields: new RequiredFields($serviceRequired ? [BookingFormField::Service] : []),
            fieldOrder: new FieldOrder($order),
            fieldLabels: new FieldLabels([]),
            occurredAt: new DateTimeImmutable,
        );
    }
}
