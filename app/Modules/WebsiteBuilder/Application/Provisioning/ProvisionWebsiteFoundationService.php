<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Provisioning;

use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionedWebsiteFoundationData;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionWebsiteFoundationCommand;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionWebsiteFoundationInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Domain\Website;

final readonly class ProvisionWebsiteFoundationService implements ProvisionWebsiteFoundationInterface
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private WebsiteRepositoryInterface $websites,
        private ClinicTransactionInterface $transaction,
    ) {}

    public function execute(ProvisionWebsiteFoundationCommand $command): ProvisionedWebsiteFoundationData
    {
        return $this->transaction->run(function () use ($command): ProvisionedWebsiteFoundationData {
            $tenantId = new TenantId($command->tenantId);
            $clinicId = new ClinicId($command->clinicId);
            $websiteId = new WebsiteId($command->websiteId);

            $clinic = $this->clinics->findByTenantId($tenantId);
            if ($clinic === null) {
                $this->clinics->save(Clinic::create(
                    $clinicId,
                    $tenantId,
                    new IanaTimezone('Asia/Kuala_Lumpur'),
                    new WeeklyOperatingHours([]),
                    $command->occurredAt,
                ));
            } elseif ($clinic->id->value !== $clinicId->value) {
                throw new InvalidWebsiteValueException('Provisioned Clinic lineage conflicts with the Tenant.');
            }

            $website = $this->websites->findByTenant($tenantId);
            if ($website === null) {
                if (count($command->sectionIds) !== 9) {
                    throw new InvalidWebsiteValueException('Website provisioning requires all governed sections.');
                }
                $this->websites->save(Website::create(
                    $websiteId,
                    $tenantId,
                    TemplateId::fromStored($command->templateId),
                    new WebsiteBranding(
                        $command->clinicName,
                        null,
                        '#0F766E',
                        '#F97316',
                        null,
                        null,
                        $command->clinicEmail,
                        $command->clinicPhone,
                        $command->clinicAddress,
                    ),
                    array_map(
                        static fn (string $sectionId): SectionId => new SectionId($sectionId),
                        $command->sectionIds,
                    ),
                    $command->occurredAt,
                ));
            } elseif ($website->id->value !== $websiteId->value) {
                throw new InvalidWebsiteValueException('Provisioned Website lineage conflicts with the Tenant.');
            }

            return new ProvisionedWebsiteFoundationData(
                $command->tenantId,
                $command->clinicId,
                $command->websiteId,
            );
        });
    }
}
