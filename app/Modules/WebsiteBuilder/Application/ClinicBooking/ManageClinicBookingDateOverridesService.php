<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicBooking;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicAvailabilityCacheInterface;
use App\Modules\WebsiteBuilder\Application\Exceptions\ClinicNotFoundException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicBookingDateOverrideRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class ManageClinicBookingDateOverridesService
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private ClinicBookingDateOverrideRepositoryInterface $overrides,
        private ClinicTransactionInterface $transactions,
        private WebsiteAuthorization $authorization,
        private PublicAvailabilityCacheInterface $availabilityCache,
    ) {}

    /** @return list<ClinicBookingDateOverrideData> */
    public function read(string $tenantId, WebsiteAuthorizationContext $authorization): array
    {
        $tenant = new TenantId($tenantId);
        $this->authorization->assertCanManageClinicBooking($authorization, $tenant);
        $clinic = $this->clinics->findByTenantId($tenant)
            ?? throw new ClinicNotFoundException('Clinic was not found in the authorized Tenant.');

        return $this->overrides->allForClinic($clinic->id);
    }

    /** @param list<array{opens_at: string, closes_at: string}> $intervals */
    public function save(
        string $tenantId,
        string $clinicId,
        WebsiteAuthorizationContext $authorization,
        string $localDate,
        bool $closed,
        array $intervals,
        int $expectedVersion,
    ): ClinicBookingDateOverrideData {
        $tenant = new TenantId($tenantId);
        $id = new ClinicId($clinicId);
        $this->authorization->assertCanManageClinicBooking($authorization, $tenant);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $localDate);
        if ($date === false || $date->format('Y-m-d') !== $localDate) {
            throw new InvalidClinicOperationalTimeException('Booking override date is invalid.');
        }
        if ($closed && $intervals !== []) {
            throw new InvalidClinicOperationalTimeException('A closed date cannot contain Booking sessions.');
        }
        if (! $closed && $intervals === []) {
            throw new InvalidClinicOperationalTimeException('An open override requires at least one Booking session.');
        }

        $validated = [];
        foreach ($intervals as $interval) {
            $validated[] = new OpeningInterval(new LocalTime($interval['opens_at']), new LocalTime($interval['closes_at']));
        }
        usort($validated, static fn (OpeningInterval $left, OpeningInterval $right): int => $left->opensAt->minutesSinceMidnight <=> $right->opensAt->minutesSinceMidnight);
        $previous = null;
        foreach ($validated as $interval) {
            if ($previous !== null && $interval->opensAt->minutesSinceMidnight < $previous->closesAt->minutesSinceMidnight) {
                throw new InvalidClinicOperationalTimeException('Booking override sessions must not overlap.');
            }
            $previous = $interval;
        }

        $override = $this->transactions->run(function () use ($tenant, $id, $localDate, $closed, $validated, $expectedVersion): ClinicBookingDateOverrideData {
            $clinic = $this->clinics->findById($tenant, $id)
                ?? throw new ClinicNotFoundException('Clinic was not found in the authorized Tenant.');
            $normalized = array_map(static fn (OpeningInterval $interval): array => [
                'opens_at' => $interval->opensAt->value,
                'closes_at' => $interval->closesAt->value,
            ], $validated);

            return $this->overrides->replace($clinic->id, $localDate, $closed, $normalized, $expectedVersion);
        });

        $this->availabilityCache->invalidateTenant($tenantId);

        return $override;
    }

    public function delete(string $tenantId, string $clinicId, WebsiteAuthorizationContext $authorization, string $localDate, int $expectedVersion): void
    {
        $tenant = new TenantId($tenantId);
        $id = new ClinicId($clinicId);
        $this->authorization->assertCanManageClinicBooking($authorization, $tenant);
        $this->transactions->run(function () use ($tenant, $id, $localDate, $expectedVersion): void {
            $clinic = $this->clinics->findById($tenant, $id)
                ?? throw new ClinicNotFoundException('Clinic was not found in the authorized Tenant.');
            $this->overrides->delete($clinic->id, $localDate, $expectedVersion);
        });
        $this->availabilityCache->invalidateTenant($tenantId);
    }
}
