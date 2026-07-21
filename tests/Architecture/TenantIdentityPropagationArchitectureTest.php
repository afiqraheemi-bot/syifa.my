<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TenantIdentityPropagationArchitectureTest extends TestCase
{
    public function test_clinic_registration_domain_never_depends_on_an_identifier_generator_interface(): void
    {
        $domain = $this->root().'/app/Modules/ClinicRegistration/Domain';

        foreach ($this->phpFilesIn($domain) as $file) {
            $source = $this->source($file);

            self::assertStringNotContainsString('GeneratorInterface', $source, $file);
            self::assertStringNotContainsString('TenantIdGenerator', $source, $file);
        }
    }

    public function test_clinic_registration_submit_receives_tenant_id_and_never_generates_it(): void
    {
        $source = $this->source($this->root().'/app/Modules/ClinicRegistration/Domain/ClinicRegistration.php');

        self::assertStringContainsString('function submit(TenantId $tenantId, DateTimeImmutable $occurredAt): void', $source);
        self::assertStringNotContainsString('random_bytes', $source);
    }

    public function test_tenant_id_generation_is_confined_to_the_application_layer(): void
    {
        self::assertFileExists($this->root().'/app/Modules/ClinicRegistration/Application/ClinicRegistrationTenantIdGeneratorInterface.php');
        self::assertFileExists($this->root().'/app/Modules/ClinicRegistration/Application/ClinicRegistrationTenantIdGenerator.php');

        $service = $this->source($this->root().'/app/Modules/ClinicRegistration/Application/SubmitClinicRegistrationService.php');
        self::assertStringContainsString('ClinicRegistrationTenantIdGeneratorInterface', $service);
    }

    public function test_no_bounded_context_imports_another_contexts_domain_tenant_id_value_object(): void
    {
        $modules = [
            'ClinicRegistration' => $this->root().'/app/Modules/ClinicRegistration',
            'Commercial' => $this->root().'/app/Modules/Commercial',
            'SubscriptionBilling' => $this->root().'/app/Modules/SubscriptionBilling',
        ];

        foreach ($modules as $ownName => $path) {
            foreach ($this->phpFilesIn($path) as $file) {
                $source = $this->source($file);

                foreach ($modules as $otherName => $otherPath) {
                    if ($otherName === $ownName) {
                        continue;
                    }

                    self::assertStringNotContainsString(
                        "App\\Modules\\{$otherName}\\Domain\\ValueObjects\\TenantId",
                        $source,
                        sprintf('%s must not import %s\'s Domain TenantId value object (%s)', $ownName, $otherName, $file),
                    );
                    self::assertStringNotContainsString(
                        "App\\Modules\\{$otherName}\\Domain\\Aggregates\\Payment\\ValueObjects\\TenantId",
                        $source,
                        sprintf('%s must not import %s\'s Payment Domain TenantId value object (%s)', $ownName, $otherName, $file),
                    );
                }
            }
        }
    }

    public function test_no_shared_kernel_directory_was_introduced_for_tenant_id(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/SharedKernel');
        self::assertDirectoryDoesNotExist($this->root().'/app/Shared');
        self::assertDirectoryDoesNotExist($this->root().'/app/Modules/Shared');
    }

    public function test_tenant_id_crosses_module_boundaries_only_as_an_opaque_string(): void
    {
        $checkout = $this->source($this->root().'/app/Modules/Commercial/Contracts/Data/CommercialOfferData.php');
        self::assertStringContainsString('public ?string $tenantId', $checkout);

        $paymentData = $this->source($this->root().'/app/Modules/SubscriptionBilling/Contracts/Payment/PaymentData.php');
        self::assertStringContainsString('public ?string $tenantId', $paymentData);

        $registrationData = $this->source($this->root().'/app/Modules/ClinicRegistration/Contracts/Data/ClinicRegistrationData.php');
        self::assertStringContainsString('public ?string $reservedTenantId', $registrationData);
    }

    public function test_no_subscription_activation_classes_tables_or_jobs_were_introduced(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Modules/SubscriptionBilling/Domain/Aggregates/Subscription/Persistence');
        self::assertFileDoesNotExist($this->root().'/app/Modules/SubscriptionBilling/Contracts/Subscription/SubscriptionActivationApplication.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/SubscriptionBilling/Infrastructure/Subscription/ActivateSubscriptionJob.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/SubscriptionBilling/Infrastructure/Subscription/PublishSubscriptionOutboxJob.php');

        foreach (glob($this->root().'/database/migrations/*/*.php') ?: [] as $migration) {
            $source = $this->source($migration);
            self::assertStringNotContainsString("Schema::create('subscriptions'", $source, $migration);
            self::assertStringNotContainsString("Schema::create('subscription_activation_applications'", $source, $migration);
            self::assertStringNotContainsString("Schema::create('subscription_activation_reconciliation_cases'", $source, $migration);
            self::assertStringNotContainsString("Schema::create('subscription_integration_outbox'", $source, $migration);
        }
    }

    public function test_new_migrations_are_additive_only_and_never_tighten_not_null_or_add_uniqueness(): void
    {
        $migrations = [
            $this->root().'/database/migrations/clinic_registration/2026_07_26_000001_add_reserved_tenant_id_to_clinic_registrations.php',
            $this->root().'/database/migrations/commercial/2026_07_26_000001_add_tenant_id_to_commercial_offers.php',
            $this->root().'/database/migrations/subscription_billing/2026_07_26_000001_add_tenant_id_to_payments.php',
        ];

        foreach ($migrations as $migration) {
            self::assertFileExists($migration);
            $source = $this->source($migration);

            self::assertStringContainsString('->nullable()', $source, $migration);
            self::assertStringNotContainsString('notNullable', $source, $migration);
            self::assertStringNotContainsString('->unique(', $source, $migration);
            self::assertStringNotContainsString('random_bytes', $source, $migration);
            self::assertStringNotContainsString("UPDATE {$migration}", $source, $migration);
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
