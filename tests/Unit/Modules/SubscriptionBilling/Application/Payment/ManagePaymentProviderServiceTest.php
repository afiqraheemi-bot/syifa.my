<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Application\Payment\ManagePaymentProviderService;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentProviderAuditTrail;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfiguration;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PaymentProviderRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ManagePaymentProviderServiceTest extends TestCase
{
    private const string ACTOR = '00000000-0000-4000-8000-000000000001';

    private const string CORRELATION_ID = '00000000-0000-4000-8000-000000000099';

    public function test_enable_succeeds_when_all_gates_pass(): void
    {
        [$service] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false),
        );

        $updated = $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertTrue($updated->enabled);
    }

    public function test_enable_rejects_when_credentials_not_configured(): void
    {
        [$service] = $this->build(
            [$this->provider('toyyibpay', false, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_enable_rejects_when_verification_not_passed(): void
    {
        [$service] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', false, false, true, true, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_enable_rejects_when_webhook_not_configured(): void
    {
        [$service] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, false, true, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_enable_rejects_when_provider_not_ready(): void
    {
        [$service] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, false, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_enable_rejects_unknown_provider_configuration(): void
    {
        [$service] = $this->build([$this->provider('toyyibpay', true, true)]);

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_enable_rejects_unregistered_provider_adapter(): void
    {
        [$service] = $this->build(
            [],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_make_default_succeeds_for_enabled_and_ready_provider(): void
    {
        [$service, $configurations] = $this->build(
            [$this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('stripe', true, true, true, true, false),
        );

        $updated = $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertTrue($updated->isDefault);
        self::assertTrue($configurations->find('stripe')?->isDefault);
    }

    public function test_make_default_rejects_disabled_provider(): void
    {
        [$service] = $this->build(
            [$this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('stripe', false, true, true, true, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_make_default_rejects_provider_that_is_not_ready(): void
    {
        [$service] = $this->build(
            [$this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('stripe', true, false, true, true, false),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_make_default_clears_previous_default_so_only_one_remains(): void
    {
        [$service, $configurations] = $this->build(
            [$this->provider('toyyibpay', true, true), $this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('toyyibpay', true, true, true, true, true),
            new PaymentProviderConfiguration('stripe', true, true, true, true, false),
        );

        $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertFalse($configurations->find('toyyibpay')?->isDefault);
        self::assertTrue($configurations->find('stripe')?->isDefault);
        self::assertSame(1, count(array_filter($configurations->all(), static fn (PaymentProviderConfiguration $c): bool => $c->isDefault)));
    }

    public function test_disable_rejects_the_current_default_provider(): void
    {
        [$service] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', true, true, true, true, true),
        );

        $this->expectException(PaymentProviderConfigurationException::class);
        $service->disable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
    }

    public function test_disabling_previous_default_succeeds_after_reassigning_default(): void
    {
        [$service, $configurations] = $this->build(
            [$this->provider('toyyibpay', true, true), $this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('toyyibpay', true, true, true, true, true),
            new PaymentProviderConfiguration('stripe', true, true, true, true, false),
        );

        try {
            $service->disable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
            self::fail('Disabling the current default must be rejected before reassignment.');
        } catch (PaymentProviderConfigurationException) {
            // Expected: the old default must remain untouched.
        }
        self::assertTrue($configurations->find('toyyibpay')?->enabled);

        $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);
        $service->disable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertFalse($configurations->find('toyyibpay')?->enabled);
        self::assertFalse($configurations->find('toyyibpay')?->isDefault);
        self::assertTrue($configurations->find('stripe')?->isDefault);
    }

    public function test_enable_records_an_audit_entry_with_actor_and_provider_key(): void
    {
        [$service, , $auditEntries] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false),
        );

        $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertCount(1, $auditEntries->entries);
        $entry = $auditEntries->entries[0];
        self::assertSame('payment_provider.enable', $entry->action);
        self::assertSame('toyyibpay', $entry->targetId);
        self::assertSame(self::ACTOR, $entry->actorIdentityId);
        self::assertSame(self::CORRELATION_ID, $entry->correlationId);
    }

    public function test_denied_enable_still_records_an_audit_entry(): void
    {
        [$service, , $auditEntries] = $this->build(
            [$this->provider('toyyibpay', false, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false),
        );

        try {
            $service->enable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);
        } catch (PaymentProviderConfigurationException) {
            // Expected.
        }

        self::assertCount(1, $auditEntries->entries);
        self::assertSame('denied', $auditEntries->entries[0]->outcome->value);
    }

    public function test_disable_records_an_audit_entry(): void
    {
        [$service, , $auditEntries] = $this->build(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', true, true, true, true, false),
        );

        $service->disable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertCount(1, $auditEntries->entries);
        self::assertSame('payment_provider.disable', $auditEntries->entries[0]->action);
        self::assertSame('succeeded', $auditEntries->entries[0]->outcome->value);
    }

    public function test_make_default_records_an_audit_entry(): void
    {
        [$service, , $auditEntries] = $this->build(
            [$this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('stripe', true, true, true, true, false),
        );

        $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertCount(1, $auditEntries->entries);
        self::assertSame('payment_provider.make_default', $auditEntries->entries[0]->action);
    }

    public function test_assess_records_an_audit_entry(): void
    {
        [$service, , $auditEntries] = $this->build([$this->provider('toyyibpay', true, true)]);

        $service->assess('toyyibpay', true, true, self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertCount(1, $auditEntries->entries);
        self::assertSame('payment_provider.assess', $auditEntries->entries[0]->action);
        self::assertSame('toyyibpay', $auditEntries->entries[0]->targetId);
    }

    public function test_audit_entries_never_contain_credential_or_secret_content(): void
    {
        [$service, , $auditEntries] = $this->build(
            [$this->provider('toyyibpay', true, true), $this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('toyyibpay', true, true, true, true, true),
            new PaymentProviderConfiguration('stripe', true, true, true, true, false),
        );

        $service->assess('toyyibpay', true, true, self::ACTOR, $this->now(), self::CORRELATION_ID);
        $service->makeDefault('stripe', self::ACTOR, $this->now(), self::CORRELATION_ID);
        $service->disable('toyyibpay', self::ACTOR, $this->now(), self::CORRELATION_ID);

        self::assertNotEmpty($auditEntries->entries);
        foreach ($auditEntries->entries as $entry) {
            $serialized = json_encode([
                'action' => $entry->action,
                'targetId' => $entry->targetId,
                'metadata' => $entry->safeMetadata,
            ]);
            self::assertIsString($serialized);
            foreach (['secret', 'credential', 'api_key', 'password', 'token'] as $forbidden) {
                self::assertStringNotContainsStringIgnoringCase($forbidden, $serialized);
            }
        }
    }

    /**
     * @param  list<PaymentProviderInterface>  $providers
     * @return array{0: ManagePaymentProviderService, 1: PaymentProviderConfigurationRepositoryInterface, 2: InMemoryAuditEntryRepository}
     */
    private function build(array $providers, PaymentProviderConfiguration ...$configurations): array
    {
        $repository = new InMemoryPaymentProviderConfigurationRepository(...$configurations);
        $registry = new PaymentProviderRegistry($providers, $repository);
        $auditEntries = new InMemoryAuditEntryRepository;
        $audit = new PaymentProviderAuditTrail(new RecordAuditEntryService($auditEntries));
        $service = new ManagePaymentProviderService($repository, $registry, new PassthroughPaymentTransaction, $audit);

        return [$service, $repository, $auditEntries];
    }

    private function provider(string $providerKey, bool $credentialsConfigured, bool $verificationPassed): PaymentProviderInterface
    {
        return new FakePaymentProvider($providerKey, $credentialsConfigured, $verificationPassed);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-21T00:00:00Z');
    }
}

final class InMemoryPaymentProviderConfigurationRepository implements PaymentProviderConfigurationRepositoryInterface
{
    /** @var array<string, PaymentProviderConfiguration> */
    private array $configurations = [];

    public function __construct(PaymentProviderConfiguration ...$configurations)
    {
        foreach ($configurations as $configuration) {
            $this->configurations[$configuration->providerKey] = $configuration;
        }
    }

    public function all(): array
    {
        return array_values($this->configurations);
    }

    public function find(string $providerKey): ?PaymentProviderConfiguration
    {
        return $this->configurations[$providerKey] ?? null;
    }

    public function default(): ?PaymentProviderConfiguration
    {
        foreach ($this->configurations as $configuration) {
            if ($configuration->isDefault) {
                return $configuration;
            }
        }

        return null;
    }

    public function save(PaymentProviderConfiguration $configuration): void
    {
        $this->configurations[$configuration->providerKey] = $configuration;
    }

    public function disable(string $providerKey): void
    {
        $current = $this->configurations[$providerKey] ?? null;
        if ($current === null) {
            return;
        }

        $this->configurations[$providerKey] = new PaymentProviderConfiguration(
            $current->providerKey,
            false,
            $current->verificationPassed,
            $current->webhookConfigured,
            $current->providerReady,
            false,
        );
    }

    public function makeDefault(string $providerKey): void
    {
        foreach ($this->configurations as $key => $configuration) {
            $this->configurations[$key] = new PaymentProviderConfiguration(
                $configuration->providerKey,
                $configuration->enabled,
                $configuration->verificationPassed,
                $configuration->webhookConfigured,
                $configuration->providerReady,
                $key === $providerKey,
            );
        }
    }

    public function findForUpdate(string $providerKey): ?PaymentProviderConfiguration
    {
        return $this->find($providerKey);
    }

    public function allForUpdate(): array
    {
        return $this->all();
    }
}

final class InMemoryAuditEntryRepository implements AuditEntryRepositoryInterface
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function append(AuditEntry $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return $auditEntry;
    }
}

final class PassthroughPaymentTransaction implements PaymentTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final class FakePaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private string $providerKey,
        private bool $credentialsConfigured,
        private bool $verificationPassed,
    ) {}

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        throw new RuntimeException('Not used by ManagePaymentProviderServiceTest.');
    }

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        throw new RuntimeException('Not used by ManagePaymentProviderServiceTest.');
    }

    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        throw new RuntimeException('Not used by ManagePaymentProviderServiceTest.');
    }

    public function credentialsConfigured(): bool
    {
        return $this->credentialsConfigured;
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        return new ProviderConfigurationVerification($this->verificationPassed, $this->verificationPassed ? 'ok' : 'verification_failed');
    }
}
