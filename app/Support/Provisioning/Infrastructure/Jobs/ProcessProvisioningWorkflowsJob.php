<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure\Jobs;

use App\Support\Provisioning\Application\ProcessProvisioningWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessProvisioningWorkflowsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ProcessProvisioningWorkflowService $processor): void
    {
        while ($processor->processNext()) {
        }
    }
}
