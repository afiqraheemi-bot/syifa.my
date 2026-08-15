<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\ClinicRegistration\Contracts\Checkout\CompleteLocalDemoAcquisitionInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class ActivateApprovedFreeTrialService
{
    public function __construct(
        private ConnectionInterface $connection,
        private CompleteLocalDemoAcquisitionInterface $activation,
    ) {}

    public function execute(string $registrationId, string $correlationId): bool
    {
        $registration = $this->connection->table('clinic_registrations')
            ->where('id', $registrationId)
            ->first(['status', 'platform_identity_id', 'selected_plan_offering_reference']);

        if ($registration === null || (string) $registration->status !== 'approved') {
            return false;
        }

        $offeringId = (string) ($registration->selected_plan_offering_reference ?? '');
        $offering = $offeringId === '' || ! Str::isUuid($offeringId) ? null : $this->connection
            ->table('commercial_catalogue_plan_offerings')
            ->where('id', $offeringId)
            ->where('status', 'active')
            ->first(['amount_minor', 'capability_configuration_reference']);

        if ($offering === null || (int) $offering->amount_minor !== 0
            || (string) $offering->capability_configuration_reference !== 'package:syifa-trial') {
            return false;
        }

        $this->activation->execute((string) $registration->platform_identity_id, $correlationId);

        return true;
    }
}
