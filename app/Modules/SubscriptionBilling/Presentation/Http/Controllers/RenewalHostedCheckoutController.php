<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\Subscription\RenewalCheckoutApplication;
use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutCommandFactoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class RenewalHostedCheckoutController
{
    public function __construct(private PaymentProviderAdministrationAuthorizationInterface $authorization) {}

    public function __invoke(
        string $renewalId,
        RenewalCheckoutCommandFactoryInterface $commands,
        RenewalCheckoutApplication $checkout,
    ): RedirectResponse {
        if (! $this->authorization->authorize()->allowed) {
            abort(403);
        }
        $command = $commands->forRenewal($renewalId, (string) Str::uuid());
        if ($command === null) {
            throw new ConflictHttpException('Renewal is not ready for hosted checkout.');
        }
        $session = $checkout->execute($command);
        if ($session instanceof PaymentSessionUnavailable) {
            throw new ConflictHttpException('Hosted checkout is temporarily unavailable.');
        }

        return redirect()->away($session->redirectAction->destination);
    }
}
