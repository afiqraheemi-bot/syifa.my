<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\SubscriptionBilling\Presentation\Http;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfiguration;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PaymentProviderRegistry;
use DateTimeImmutable;
use RuntimeException;
use Tests\TestCase as LaravelTestCase;

final class PaymentProviderAdministrationHttpDeliveryTest extends LaravelTestCase
{
    private const string BASE = '/api/v1/platform/payment-providers';

    public function test_every_route_fails_closed_without_an_authenticated_platform_session(): void
    {
        foreach ($this->routes() as [$method, $uri]) {
            $response = $this->call($method, $uri);
            self::assertSame(401, $response->getStatusCode(), "{$method} {$uri}");
        }
    }

    public function test_website_designer_is_rejected(): void
    {
        $this->actingAsPrincipal('website_designer');

        $this->getJson(self::BASE)->assertStatus(403);
    }

    public function test_clinic_owner_is_rejected(): void
    {
        $this->actingAsPrincipal('clinic_owner');

        $this->getJson(self::BASE)->assertStatus(403);
    }

    public function test_super_admin_can_list_provider_configurations(): void
    {
        $this->actingAsPrincipal('super_admin');
        $this->bindPaymentInfrastructure(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', true, true, true, true, true),
        );

        $response = $this->getJson(self::BASE);

        $response->assertOk();
        $response->assertJsonPath('data.0.provider_key', 'toyyibpay');
        $response->assertJsonPath('data.0.is_default', true);
    }

    public function test_super_admin_can_enable_a_provider(): void
    {
        $this->actingAsPrincipal('super_admin');
        $this->bindPaymentInfrastructure(
            [$this->provider('toyyibpay', true, true)],
            new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false),
        );

        $response = $this->postJson(self::BASE.'/toyyibpay/enable');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
    }

    public function test_enable_rejects_when_activation_gates_are_not_satisfied_and_reveals_no_credential_values(): void
    {
        $this->actingAsPrincipal('super_admin');
        $this->bindPaymentInfrastructure(
            [$this->provider('stripe', false, true)],
            new PaymentProviderConfiguration('stripe', false, true, true, true, false),
        );

        $response = $this->postJson(self::BASE.'/stripe/enable');

        $response->assertStatus(409);
        $body = $response->getContent();
        self::assertIsString($body);
        // The message legitimately names the "credentials" gate category; what
        // must never leak is an actual secret-shaped value.
        foreach (['sk_', 'whsec_', 'secret=', 'api_key='] as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase($forbidden, $body);
        }
    }

    public function test_response_never_serializes_credentials_or_secrets(): void
    {
        $this->actingAsPrincipal('super_admin');
        $this->bindPaymentInfrastructure(
            [$this->provider('stripe', true, true)],
            new PaymentProviderConfiguration('stripe', true, true, true, true, false),
        );

        $response = $this->getJson(self::BASE);
        $response->assertOk();

        $body = $response->getContent();
        self::assertIsString($body);
        foreach (['secret', 'credential', 'api_key', 'webhook_secret', 'password'] as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase($forbidden, $body);
        }
    }

    /** @return list<array{0:string,1:string}> */
    private function routes(): array
    {
        return [
            ['GET', self::BASE],
            ['POST', self::BASE.'/stripe/assess'],
            ['POST', self::BASE.'/stripe/enable'],
            ['POST', self::BASE.'/stripe/disable'],
            ['POST', self::BASE.'/stripe/default'],
        ];
    }

    private function actingAsPrincipal(string $role): void
    {
        $this->app->instance(PlatformPrincipalResolverInterface::class, new class($role) implements PlatformPrincipalResolverInterface
        {
            public function __construct(private string $role) {}

            public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
            {
                return new PlatformPrincipal('00000000-0000-4000-8000-0000000000aa', $this->role, 'Test Principal');
            }
        });
    }

    /** @param  list<PaymentProviderInterface>  $providers */
    private function bindPaymentInfrastructure(array $providers, PaymentProviderConfiguration ...$configurations): void
    {
        $repository = new class(...$configurations) implements PaymentProviderConfigurationRepositoryInterface
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
                    $current->providerKey, false, $current->verificationPassed, $current->webhookConfigured, $current->providerReady, false,
                );
            }

            public function makeDefault(string $providerKey): void
            {
                foreach ($this->configurations as $key => $configuration) {
                    $this->configurations[$key] = new PaymentProviderConfiguration(
                        $configuration->providerKey, $configuration->enabled, $configuration->verificationPassed,
                        $configuration->webhookConfigured, $configuration->providerReady, $key === $providerKey,
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
        };

        $this->app->instance(PaymentProviderConfigurationRepositoryInterface::class, $repository);
        $this->app->instance(PaymentProviderRegistryInterface::class, new PaymentProviderRegistry($providers, $repository));
        $this->app->instance(PaymentTransactionInterface::class, new class implements PaymentTransactionInterface
        {
            public function run(callable $operation): mixed
            {
                return $operation();
            }
        });
        $this->app->instance(AuditEntryRepositoryInterface::class, new class implements AuditEntryRepositoryInterface
        {
            public function append(AuditEntry $auditEntry): AuditEntry
            {
                return $auditEntry;
            }
        });
    }

    private function provider(string $providerKey, bool $credentialsConfigured, bool $verificationPassed): PaymentProviderInterface
    {
        return new class($providerKey, $credentialsConfigured, $verificationPassed) implements PaymentProviderInterface
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
                throw new RuntimeException('Not used by PaymentProviderAdministrationHttpDeliveryTest.');
            }

            public function verify(string $providerPaymentReference): ProviderPaymentVerification
            {
                throw new RuntimeException('Not used by PaymentProviderAdministrationHttpDeliveryTest.');
            }

            public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
            {
                throw new RuntimeException('Not used by PaymentProviderAdministrationHttpDeliveryTest.');
            }

            public function credentialsConfigured(): bool
            {
                return $this->credentialsConfigured;
            }

            public function verifyConfiguration(): ProviderConfigurationVerification
            {
                return new ProviderConfigurationVerification($this->verificationPassed, $this->verificationPassed ? 'ok' : 'verification_failed');
            }
        };
    }
}
