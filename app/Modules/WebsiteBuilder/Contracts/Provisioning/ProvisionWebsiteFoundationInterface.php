<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Provisioning;

interface ProvisionWebsiteFoundationInterface
{
    public function execute(ProvisionWebsiteFoundationCommand $command): ProvisionedWebsiteFoundationData;
}
