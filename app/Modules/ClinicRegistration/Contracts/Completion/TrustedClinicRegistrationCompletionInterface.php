<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Completion;

use App\Modules\ClinicRegistration\Contracts\Commands\CompleteClinicRegistrationFromTrustedHandoffCommand;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;

interface TrustedClinicRegistrationCompletionInterface
{
    public function execute(CompleteClinicRegistrationFromTrustedHandoffCommand $command): ClinicRegistrationData;
}
