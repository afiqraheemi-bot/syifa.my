<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PaymentCoreArchitectureTest extends TestCase
{
    public function test_payment_core_has_one_aggregate_root_and_no_attempt_repository(): void
    {
        $module = $this->root().'/app/Modules/SubscriptionBilling';

        self::assertFileExists($module.'/Domain/Aggregates/Payment/Payment.php');
        self::assertFileExists($module.'/Domain/Aggregates/Payment/PaymentAttempt.php');
        self::assertFileDoesNotExist($module.'/Contracts/Payment/PaymentAttemptRepositoryInterface.php');

        foreach ($this->phpFilesIn($module) as $file) {
            self::assertStringNotContainsString('PaymentAttemptRepository', $this->source($file), $file);
            self::assertStringNotContainsString('WebhookReceipt extends', $this->source($file), $file);
            self::assertStringNotContainsString('WebhookReceiptRepository', $this->source($file), $file);
        }
    }

    public function test_payment_core_is_provider_neutral_and_contains_no_gateway_sdk(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/SubscriptionBilling') as $file) {
            $source = $this->source($file);

            foreach ([
                'Billplz',
                'Stripe',
                'ToyyibPay',
                'FPX',
                'PaymentGateway',
                'GatewaySdk',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_payment_core_does_not_own_subscription_tenant_or_onboarding_logic(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/SubscriptionBilling/Domain/Aggregates/Payment') as $file) {
            $source = $this->source($file);

            foreach ([
                'SubscriptionActivated',
                'TenantProvision',
                'OnboardingJob',
                'Invoice',
                'Receipt',
                'Refund',
                'Renewal',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_payment_domain_is_framework_independent(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/SubscriptionBilling/Domain/Aggregates/Payment') as $file) {
            $source = $this->source($file);

            foreach (['Illuminate\\', 'Laravel\\', 'Infrastructure\\', 'Presentation\\', 'DB::', 'Schema::'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_payment_migration_creates_only_approved_payment_tables(): void
    {
        $source = $this->source($this->root().'/database/migrations/subscription_billing/2026_07_21_000002_create_payment_core_tables.php');

        foreach (['payments', 'payment_attempts'] as $table) {
            self::assertStringContainsString("Schema::create('{$table}'", $source);
        }

        self::assertStringNotContainsString('payment_provider_webhook_receipts', $source);

        foreach (['invoices', 'subscriptions', 'tenants', 'onboarding_jobs', 'refunds'] as $forbiddenTable) {
            self::assertStringNotContainsString("Schema::create('{$forbiddenTable}'", $source);
        }
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function source(string $file): string
    {
        $source = file_get_contents($file);
        self::assertIsString($source);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
