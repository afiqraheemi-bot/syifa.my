<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\Subscription\RenewalCheckoutApplication;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ClinicOwnerRenewalCheckoutCommandFactoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

final readonly class ClinicOwnerRenewalHostedCheckoutController
{
    public function __invoke(
        Request $request,
        ClinicOwnerRenewalCheckoutCommandFactoryInterface $commands,
        RenewalCheckoutApplication $checkout,
    ): RedirectResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner tenant context was not established.');
        }

        $command = $commands->forTenant($context->tenantId, (string) Str::uuid());
        if ($command === null) {
            return redirect()->route('dashboard.subscription')
                ->with('subscription_error', 'Renewal checkout is not available for this subscription.');
        }

        $session = $checkout->execute($command);
        if ($session instanceof PaymentSessionUnavailable) {
            return redirect()->route('dashboard.subscription')
                ->with('subscription_error', 'Renewal checkout is temporarily unavailable. Please try again.');
        }

        return redirect()->away($session->redirectAction->destination);
    }
}
