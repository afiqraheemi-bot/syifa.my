<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentData;
use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardPageView;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class ClinicOwnerSubscriptionPage
{
    public function __construct(
        private ClinicOwnerSubscriptionDetailReadInterface $subscriptions,
        private BillingDocumentReadInterface $documents,
    ) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner tenant context was not established.');
        }

        $detail = $this->subscriptions->detailForTenant($context->tenantId);
        $isTrial = $detail !== null && str_contains(mb_strtolower($detail->planName), 'trial');
        $trialDaysRemaining = $isTrial
            ? max(0, CarbonImmutable::today()->diffInDays(CarbonImmutable::parse($detail->endsOn), false) + 1)
            : null;

        return new DashboardPageView('SubscriptionBilling/Dashboard/ClinicOwnerSubscriptionDetail', [
            'navigation' => ClinicOwnerDashboardNavigation::items('subscription'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'subscription', 'label' => 'Subscription'],
            ],
            'pageTitle' => 'Subscription',
            'pageDescription' => 'View your clinic plan, current term, and renewal availability.',
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'subscription' => $detail === null ? null : [
                'plan' => $detail->planName,
                'status' => $this->label($detail->status),
                'startsOn' => $detail->startsOn,
                'endsOn' => $detail->endsOn,
                'billingCycle' => $detail->billingCycleName,
                'renewalStatus' => $detail->renewalEligible ? 'Renewal available' : 'Not available',
                'latestPaymentStatus' => $detail->latestPaymentStatus === null
                    ? 'Not available'
                    : $this->label($detail->latestPaymentStatus),
                'isTrial' => $isTrial,
                'trialDaysRemaining' => $trialDaysRemaining,
            ],
            'upgradePlans' => $isTrial ? [
                [
                    'name' => 'Syifa Basic',
                    'price' => 'RM299 / tahun',
                    'description' => 'Website profesional, kandungan dan tempahan pesakit.',
                    'recommended' => false,
                    'href' => 'https://wa.me/60134079388?text='.rawurlencode('Saya ingin upgrade daripada Syifa Trial ke Syifa Basic dan teruskan pembayaran.'),
                ],
                [
                    'name' => 'Syifa Standard',
                    'price' => 'RM399 / tahun',
                    'description' => 'Semua ciri Basic bersama SYIFA AI dan custom domain.',
                    'recommended' => true,
                    'href' => 'https://wa.me/60134079388?text='.rawurlencode('Saya ingin upgrade daripada Syifa Trial ke Syifa Standard dan teruskan pembayaran.'),
                ],
            ] : [],
            'renewal' => $detail?->renewalEligible === true ? [
                'label' => 'Renew Subscription',
                'action' => route('dashboard.subscription.renewal-checkout'),
                'csrfToken' => csrf_token(),
            ] : null,
            'documents' => array_map(fn (BillingDocumentData $document): array => [
                'paymentId' => $document->paymentId,
                'invoiceNumber' => $document->invoiceNumber,
                'receiptNumber' => $document->receiptNumber,
                'purpose' => ucwords(str_replace('_', ' ', $document->purpose)),
                'amount' => $document->currency.' '.number_format($document->amountMinor / 100, 2, '.', ','),
                'status' => ucwords(str_replace('_', ' ', $document->paymentStatus)),
                'issuedAt' => $this->dateTime($document->issuedAt),
                'invoiceHref' => route('dashboard.subscription.invoices.show', $document->paymentId),
                'receiptHref' => $document->receiptNumber === null
                    ? null
                    : route('dashboard.subscription.receipts.show', $document->paymentId),
            ], $this->documents->listForTenant($context->tenantId)),
            'feedback' => [
                'error' => session('subscription_error'),
            ],
        ]);
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function dateTime(string $value): string
    {
        return CarbonImmutable::parse($value)
            ->timezone((string) config('app.timezone', 'Asia/Kuala_Lumpur'))
            ->format('d M Y, g:i A');
    }
}
