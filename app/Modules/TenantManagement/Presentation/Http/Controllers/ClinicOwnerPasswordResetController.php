<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerPasswordResetLinkIssuerInterface;
use App\Modules\TenantManagement\Presentation\Http\Requests\ClinicOwnerForgotPasswordRequest;
use Illuminate\Http\JsonResponse;

final readonly class ClinicOwnerPasswordResetController
{
    public function __invoke(
        ClinicOwnerForgotPasswordRequest $request,
        ClinicOwnerPasswordResetLinkIssuerInterface $links,
    ): JsonResponse {
        /** @var array{email: string} $input */
        $input = $request->safe()->only(['email']);
        $links->issueForEmail($input['email']);

        return response()->json(['data' => ['acknowledged' => true]]);
    }
}
