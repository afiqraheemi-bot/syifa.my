<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentData;
use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardPageView;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class BillingDocumentPage
{
    public function __construct(private BillingDocumentReadInterface $documents) {}

    public function forClinicOwner(mixed $context, string $paymentId, string $type): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Clinic Owner tenant context was not established.');
        }

        return $this->view(
            $this->documents->detailForTenant($paymentId, $context->tenantId),
            $type,
            route('dashboard.subscription'),
        );
    }

    public function forSuperAdmin(mixed $context, string $paymentId, string $type): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        $document = $this->documents->detail($paymentId);

        return $this->view(
            $document,
            $type,
            $document === null
                ? route('dashboard.billing')
                : route('dashboard.billing.subscriptions.show', $document->subscriptionId),
        );
    }

    private function view(?BillingDocumentData $document, string $type, string $backHref): DashboardPageView
    {
        if ($document === null || ($type === 'receipt' && $document->receiptNumber === null)) {
            throw new NotFoundHttpException('Billing document was not found.');
        }

        return new DashboardPageView('SubscriptionBilling/Dashboard/BillingDocument', [
            'documentType' => $type,
            'documentNumber' => $type === 'receipt' ? $document->receiptNumber : $document->invoiceNumber,
            'backHref' => $backHref,
            'document' => [
                'clinicName' => $document->clinicName,
                'tenantReference' => $this->shortReference('TEN', $document->tenantId),
                'subscriptionReference' => $this->shortReference('SUB', $document->subscriptionId),
                'paymentReference' => $this->shortReference('PAY', $document->paymentId),
                'purpose' => $this->label($document->purpose),
                'plan' => $document->planName,
                'billingCycle' => $document->billingCycleName,
                'period' => $document->periodStartsOn.' – '.$document->periodEndsOn,
                'amount' => $document->currency.' '.number_format($document->amountMinor / 100, 2, '.', ','),
                'status' => $this->label($document->paymentStatus),
                'issuedAt' => $this->dateTime($document->issuedAt),
                'paidAt' => $document->paidAt === null ? null : $this->dateTime($document->paidAt),
                'provider' => $document->providerKey === null ? 'Not assigned' : $this->label($document->providerKey),
                'providerReference' => $document->providerReference,
            ],
        ]);
    }

    private function shortReference(string $prefix, string $reference): string
    {
        $value = trim($reference);
        $withoutKnownPrefix = preg_replace(
            '/^(payment|pay|subscription|sub|tenant|ten)[-_]?/i',
            '',
            $value,
        ) ?? $value;
        $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $withoutKnownPrefix));
        $suffix = strlen($compact) > 12 ? substr($compact, -8) : $compact;

        return $prefix.'-'.$suffix;
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
