<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionStatusReadInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicInitialAcquisitionReturnController
{
    public function __construct(
        private RegistrationTrackingCredentialInterface $tracking,
        private PublicInitialAcquisitionStatusReadInterface $payments,
    ) {}

    public function __invoke(): Response
    {
        $registrationReference = $this->tracking->current();
        $payment = $registrationReference === null
            ? null
            : $this->payments->forRegistration($registrationReference);

        if ($payment === null) {
            throw new NotFoundHttpException;
        }

        $presentation = $this->presentation($payment->paymentStatus);

        return Inertia::render('ClinicRegistration/PublicPaymentReturn', [
            'paymentStatus' => $payment->paymentStatus,
            'statusTone' => $presentation['tone'],
            'title' => $presentation['title'],
            'message' => $presentation['message'],
            'formattedAmount' => sprintf(
                '%s %s',
                $payment->currency,
                number_format($payment->amountMinor / 100, 2, '.', ','),
            ),
            'lastChangedAt' => $payment->lastChangedAt,
            'offersUrl' => route('clinic-registration.offers'),
            'refreshUrl' => route('clinic-registration.payment-return'),
            'homeUrl' => route('root', [], false),
        ]);
    }

    /** @return array{tone: string, title: string, message: string} */
    private function presentation(string $status): array
    {
        return match ($status) {
            'succeeded' => [
                'tone' => 'success',
                'title' => 'Payment verified',
                'message' => 'Thank you. Your clinic workspace is being prepared. We will send the Clinic Owner setup instructions to the registered email address.',
            ],
            'failed', 'cancelled', 'expired' => [
                'tone' => 'error',
                'title' => 'Payment was not completed',
                'message' => 'No clinic workspace has been activated. You may return to the approved offer and safely try again.',
            ],
            default => [
                'tone' => 'pending',
                'title' => 'Payment is being verified',
                'message' => 'The provider response is still being verified. Refresh this page shortly; do not start another payment while verification is in progress.',
            ],
        };
    }
}
