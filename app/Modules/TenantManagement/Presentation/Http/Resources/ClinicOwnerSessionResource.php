<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Resources;

use App\Modules\TenantManagement\Contracts\Session\AuthenticatedClinicOwnerSessionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuthenticatedClinicOwnerSessionData */
final class ClinicOwnerSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'authenticated' => true,
            'role' => $this->role,
            'tenant' => ['id' => $this->tenantId],
            'session' => [
                'idle_expires_at' => $this->idleExpiresAt->format(DATE_RFC3339),
                'absolute_expires_at' => $this->absoluteExpiresAt->format(DATE_RFC3339),
            ],
        ];
    }
}
