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
        }

        // ProviderWebhookReceipt is an Infrastructure/Contracts idempotency
        // record, never an entity inside the Payment aggregate (docs/31). Its
        // repository is therefore approved only outside Domain.
        foreach ($this->phpFilesIn($module.'/Domain') as $file) {
            self::assertStringNotContainsString('WebhookReceiptRepository', $this->source($file), $file);
            self::assertStringNotContainsString('Receipt', $this->source($file), $file);
        }
    }

    public function test_provider_webhook_receipt_persistence_belongs_only_to_contracts_and_infrastructure(): void
    {
        $module = $this->root().'/app/Modules/SubscriptionBilling';

        self::assertFileExists($module.'/Contracts/Payment/ProviderWebhookReceipt.php');
        self::assertFileExists($module.'/Contracts/Payment/ProviderWebhookReceiptStatus.php');
        self::assertFileExists($module.'/Contracts/Payment/ProviderWebhookReceiptRepositoryInterface.php');
        self::assertFileExists($module.'/Infrastructure/Payment/PostgresProviderWebhookReceiptRepository.php');
        self::assertFileDoesNotExist($module.'/Domain/Aggregates/Payment/ProviderWebhookReceipt.php');

        $repository = $this->source($module.'/Infrastructure/Payment/PostgresProviderWebhookReceiptRepository.php');
        self::assertStringNotContainsString('DB::transaction', $repository);
        self::assertStringNotContainsString('->transaction(', $repository);

        // The receipt id is an opaque surrogate; only the (provider_key,
        // provider_event_id) unique index is the idempotency guard. No
        // deterministic hash-derivation helper may return as a shortcut.
        self::assertStringNotContainsString('deterministicId', $repository);
        self::assertStringNotContainsString("hash('sha256'", $repository);
        self::assertStringContainsString('Str::uuid()', $repository);
    }

    public function test_provider_webhook_receipt_types_do_not_accumulate_business_or_orchestration_responsibilities(): void
    {
        $root = $this->root().'/app/Modules/SubscriptionBilling/Contracts/Payment';

        foreach (['ProviderWebhookReceipt.php', 'ProviderWebhookReceiptStatus.php'] as $file) {
            $path = $root.'/'.$file;
            self::assertFileExists($path);
            $source = $this->source($path);

            foreach ([
                // Payment aggregate or Payment lifecycle services.
                'Domain\\Aggregates\\Payment',
                'Application\\Payment\\',
                'Domain\\Aggregates\\Payment\\PaymentAttempt',
                // Subscription and CommercialOffer (namespace-qualified, not
                // the bare word, since the module itself is "SubscriptionBilling").
                'Domain\\Aggregates\\Subscription\\',
                'SubscriptionActivated',
                'CommercialOffer',
                // Provider SDK classes/namespaces and transport implementations.
                'Infrastructure\\Payment\\Stripe',
                'Infrastructure\\Payment\\ToyyibPay',
                'StripePaymentProvider',
                'ToyyibPayPaymentProvider',
                'PaymentProviderInterface',
                'PaymentProviderRegistry',
                'PaymentProviderTransportException',
                // Policy and Service classes.
                'Policy',
                'Service',
                // Delivery-layer, queue, and event-listener concerns.
                'Controller',
                'Route',
                'Middleware',
                'ShouldQueue',
                'Illuminate\\Bus\\',
                'Illuminate\\Queue\\',
                'Illuminate\\Events\\',
                'Listener',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, "{$file} must not reference {$forbidden}.");
            }
        }
    }

    public function test_payment_core_is_provider_neutral_and_contains_no_gateway_sdk(): void
    {
        foreach (['Domain', 'Application', 'Contracts'] as $layer) {
            foreach ($this->phpFilesIn($this->root().'/app/Modules/SubscriptionBilling/'.$layer) as $file) {
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

    public function test_webhook_receiving_stops_before_financial_verification_or_state_transition(): void
    {
        $root = $this->root().'/app/Modules/SubscriptionBilling';
        $service = $this->source($root.'/Application/Payment/ReceivePaymentProviderWebhookService.php');
        $controller = $this->source($root.'/Presentation/Http/Controllers/PaymentProviderWebhookController.php');

        foreach (['Illuminate\\Http', 'PaymentRepositoryInterface', 'PaymentTransactionInterface', 'MarkPayment', 'Domain\\Aggregates\\Subscription', 'SubscriptionActivated', 'verify('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $service, $forbidden);
        }

        foreach (['hash_hmac', 'hash_equals', 'Stripe-Signature', 'parse_str', 'PaymentRepositoryInterface', 'MarkPayment', 'Domain\\Aggregates\\Subscription', 'SubscriptionActivated'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller, $forbidden);
        }

        self::assertStringContainsString('getContent()', $controller);
        self::assertStringContainsString('forExistingAttempt', $service);
        self::assertStringContainsString('ProviderWebhookReceiptRepositoryInterface', $service);
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

    public function test_provider_webhook_receipts_have_their_own_dedicated_migration(): void
    {
        $path = $this->root().'/database/migrations/subscription_billing/2026_07_23_000001_create_payment_provider_webhook_receipts.php';
        self::assertFileExists($path);

        $source = $this->source($path);
        self::assertStringContainsString("Schema::create('payment_provider_webhook_receipts'", $source);
        self::assertStringContainsString("unique(['provider_key', 'provider_event_id'])", $source);

        foreach (['invoices', 'subscriptions', 'tenants', 'onboarding_jobs', 'refunds', 'payments', 'payment_attempts'] as $forbiddenTable) {
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
