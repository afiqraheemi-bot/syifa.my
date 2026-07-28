<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CancelAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\EnableAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

final readonly class SuperAdminSubscriptionOperationController
{
    public function renew(Request $request, string $subscriptionId, ManualRenewSubscriptionInterface $renewals): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:160'],
        ]);
        $context = $this->context($request);
        $result = $renewals->renew(new ManualRenewSubscriptionCommand(
            $subscriptionId,
            $context->identityId,
            (string) $validated['idempotency_key'],
            (int) $validated['expected_version'],
            (string) Str::uuid(),
            new DateTimeImmutable,
        ));

        return back()->with('operation', $result->code);
    }

    public function enableAutoRenew(Request $request, string $subscriptionId, EnableAutoRenewInterface $autoRenew): RedirectResponse
    {
        $command = $this->autoRenewCommand($request, $subscriptionId);

        return back()->with('operation', $autoRenew->enable($command)->code);
    }

    public function disableAutoRenew(Request $request, string $subscriptionId, CancelAutoRenewInterface $autoRenew): RedirectResponse
    {
        $command = $this->autoRenewCommand($request, $subscriptionId);

        return back()->with('operation', $autoRenew->cancel($command)->code);
    }

    private function autoRenewCommand(Request $request, string $subscriptionId): AutoRenewCommand
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->context($request);

        return new AutoRenewCommand(
            $subscriptionId,
            $context->identityId,
            (int) $validated['expected_version'],
            (string) Str::uuid(),
            new DateTimeImmutable,
        );
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin authorization context was not established.');
        }

        return $context;
    }
}
