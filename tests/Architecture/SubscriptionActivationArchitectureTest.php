<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SubscriptionActivationArchitectureTest extends TestCase
{
    public function test_application_owns_orchestration_and_repositories_contain_no_activation_policy(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling';
        $service = file_get_contents($root.'/Application/Subscription/ActivateSubscriptionFromVerifiedPaymentService.php');
        self::assertIsString($service);
        foreach (['Subscription::create', '->activate(', '->save(', '->complete(', '->record('] as $step) {
            self::assertStringContainsString($step, $service);
        }

        foreach (glob($root.'/Infrastructure/Persistence/Repositories/*SubscriptionActivation*.php') ?: [] as $repository) {
            $source = file_get_contents($repository);
            self::assertIsString($source);
            self::assertStringNotContainsString('Subscription::create', $source);
            self::assertStringNotContainsString('->activate(', $source);
        }
    }

    public function test_domain_has_no_orm_shared_kernel_or_provider_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Domain/Aggregates/Subscription';
        foreach (glob($root.'/**/*.php') ?: [] as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            foreach (['Illuminate\\', 'SharedKernel', 'Stripe', 'ToyyibPay'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_subscription_activation_delivery_is_wired_through_infrastructure_only(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root.'/app/Modules/SubscriptionBilling/Infrastructure/Subscription/Jobs/ActivateSubscriptionJob.php');
        self::assertFileExists($root.'/database/migrations/subscription_billing/2026_07_29_000001_create_subscription_integration_outbox.php');

        // Dispatch/publish concerns belong to Infrastructure (the listener + job dispatcher),
        // never to the Application-layer activation service itself.
        $service = file_get_contents($root.'/app/Modules/SubscriptionBilling/Application/Subscription/ActivateSubscriptionFromVerifiedPaymentService.php');
        self::assertIsString($service);
        self::assertStringNotContainsString('Dispatcher', $service);
        self::assertStringNotContainsString('publish(', $service);
    }
}
