<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Infrastructure\TenantRouting;

use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingData;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\AdminHostParser;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\Exceptions\InvalidTenantAdminDomainConfigurationException;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\TenantAdminHostTrustedTenantSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantAdminHostTrustedTenantSelectorTest extends TestCase
{
    public function test_valid_admin_host_resolves_through_the_routing_lookup(): void
    {
        $lookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'klinik-zahra',
            'active',
        ));
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $lookup,
        );

        $selection = $selector->select('klinik-zahra.app.syifa.my');

        self::assertNotNull($selection);
        self::assertSame($this->uuid(1), $selection->tenantId);
        self::assertSame(['klinik-zahra'], $lookup->resolvedLabels);
    }

    public function test_localhost_resolves_only_the_server_configured_demo_routing_label_when_enabled(): void
    {
        $lookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'demo-clinic',
            'active',
        ));
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $lookup,
            true,
            'localhost',
            'demo-clinic',
        );

        $selection = $selector->select('localhost');

        self::assertNotNull($selection);
        self::assertSame($this->uuid(1), $selection->tenantId);
        self::assertSame(['demo-clinic'], $lookup->resolvedLabels);
    }

    public function test_localhost_is_rejected_when_local_selection_is_disabled_for_production(): void
    {
        $lookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'demo-clinic',
            'active',
        ));
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $lookup,
            false,
            'localhost',
            'demo-clinic',
        );

        self::assertNull($selector->select('localhost'));
        self::assertSame([], $lookup->resolvedLabels);
    }

    #[DataProvider('localSpoofingReferences')]
    public function test_local_selection_rejects_every_browser_supplied_tenant_spoof(
        string $selectorReference,
    ): void {
        $lookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'demo-clinic',
            'active',
        ));
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $lookup,
            true,
            'localhost',
            'demo-clinic',
        );

        self::assertNull($selector->select($selectorReference));
        self::assertSame([], $lookup->resolvedLabels);
    }

    /** @return iterable<string, array{string}> */
    public static function localSpoofingReferences(): iterable
    {
        yield 'query tenant id' => ['localhost?tenant_id=00000000-0000-4000-8000-000000000099'];
        yield 'query routing label' => ['localhost?tenant=other-clinic'];
        yield 'header-like value' => ['localhost,other-clinic'];
        yield 'cookie-like value' => ['localhost; tenant_id=other-clinic'];
        yield 'IP alias is not configured host' => ['127.0.0.1'];
        yield 'case variation' => ['LOCALHOST'];
        yield 'whitespace' => [' localhost'];
    }

    #[DataProvider('invalidHosts')]
    public function test_invalid_admin_host_fails_closed_without_lookup(string $host): void
    {
        $lookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'klinik-zahra',
            'active',
        ));
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $lookup,
        );

        self::assertNull($selector->select($host));
        self::assertSame([], $lookup->resolvedLabels);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidHosts(): iterable
    {
        yield 'empty' => [''];
        yield 'bare admin base' => ['app.syifa.my'];
        yield 'bare platform domain' => ['syifa.my'];
        yield 'public website host' => ['klinik-zahra.syifa.my'];
        yield 'custom domain' => ['klinik-zahra.custom-domain.com'];
        yield 'leading hyphen in tenant label' => ['-klinik.app.syifa.my'];
        yield 'trailing hyphen in tenant label' => ['klinik-.app.syifa.my'];
        yield 'uppercase' => ['Klinik-zahra.app.syifa.my'];
        yield 'leading whitespace' => [' klinik-zahra.app.syifa.my'];
        yield 'trailing whitespace' => ['klinik-zahra.app.syifa.my '];
        yield 'trailing dot' => ['klinik-zahra.app.syifa.my.'];
        yield 'duplicate dot' => ['klinik-zahra..app.syifa.my'];
        yield 'scheme' => ['https://klinik-zahra.app.syifa.my'];
        yield 'path' => ['klinik-zahra.app.syifa.my/login'];
        yield 'query' => ['klinik-zahra.app.syifa.my?tenant_id=other'];
        yield 'fragment' => ['klinik-zahra.app.syifa.my#login'];
        yield 'userinfo' => ['owner@klinik-zahra.app.syifa.my'];
        yield 'IPv4 address' => ['127.0.0.1'];
        yield 'IPv6 address' => ['::1'];
        yield 'localhost' => ['localhost'];
        yield 'punycode tenant label' => ['xn--klinik-9za.app.syifa.my'];
        yield 'punycode base label' => ['klinik-zahra.xn--syifa-qza.my'];
        yield 'non-ASCII' => ['klinik-zahrá.app.syifa.my'];
        yield 'unexpected port' => ['klinik-zahra.app.syifa.my:443'];
        yield 'additional label' => ['other.klinik-zahra.app.syifa.my'];
        yield 'complete URL with port and path' => ['https://klinik-zahra.app.syifa.my:443/login'];
        yield 'reserved routing label' => ['admin.app.syifa.my'];
        yield 'email cannot select tenant' => ['owner@example.test'];
        yield 'browser tenant identifier cannot select tenant' => ['00000000-0000-4000-8000-000000000001'];
    }

    public function test_unknown_label_and_mismatched_lookup_data_are_rejected_generically(): void
    {
        $unknownLookup = $this->recordingLookup(null);
        $unknownSelector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $unknownLookup,
        );

        self::assertNull($unknownSelector->select('unknown-clinic.app.syifa.my'));
        self::assertSame(['unknown-clinic'], $unknownLookup->resolvedLabels);

        $mismatchedLookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'other-clinic',
            'active',
        ));
        $mismatchedSelector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            $mismatchedLookup,
        );

        self::assertNull($mismatchedSelector->select('klinik-zahra.app.syifa.my'));
    }

    #[DataProvider('invalidConfigurations')]
    public function test_empty_or_malformed_configuration_fails_loudly_without_exposing_values(
        array $adminBaseDomains,
    ): void {
        try {
            new AdminHostParser($adminBaseDomains);
            self::fail('Invalid Tenant Admin domain configuration must fail loudly.');
        } catch (InvalidTenantAdminDomainConfigurationException $exception) {
            self::assertSame(
                'Tenant Admin base-domain configuration is invalid.',
                $exception->getMessage(),
            );

            foreach ($adminBaseDomains as $adminBaseDomain) {
                if ($adminBaseDomain !== '') {
                    self::assertStringNotContainsString($adminBaseDomain, $exception->getMessage());
                }
            }
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'empty list' => [[]];
        yield 'empty domain' => [['']];
        yield 'comma-space entry' => [['app.syifa.my', ' admin.syifa.my']];
        yield 'leading whitespace' => [[' app.syifa.my']];
        yield 'trailing whitespace' => [['app.syifa.my ']];
        yield 'scheme' => [['https://app.syifa.my']];
        yield 'path' => [['app.syifa.my/login']];
        yield 'query' => [['app.syifa.my?mode=admin']];
        yield 'userinfo' => [['owner@app.syifa.my']];
        yield 'uppercase' => [['APP.syifa.my']];
        yield 'port' => [['app.syifa.my:443']];
        yield 'localhost' => [['localhost']];
        yield 'punycode' => [['xn--syifa-qza.my']];
        yield 'non-ASCII' => [['äpp.syifa.my']];
        yield 'one malformed entry invalidates all' => [['app.syifa.my', 'invalid..domain']];
    }

    public function test_multiple_approved_base_domains_are_supported(): void
    {
        $lookup = $this->recordingLookup(new TenantAdminRoutingData(
            $this->uuid(1),
            'klinik-zahra',
            'active',
        ));
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my', 'admin.syifa.example']),
            $lookup,
        );

        self::assertNotNull($selector->select('klinik-zahra.app.syifa.my'));
        self::assertNotNull($selector->select('klinik-zahra.admin.syifa.example'));
        self::assertSame(['klinik-zahra', 'klinik-zahra'], $lookup->resolvedLabels);
    }

    private function recordingLookup(
        ?TenantAdminRoutingData $result,
    ): RecordingTenantAdminRoutingLookup {
        return new RecordingTenantAdminRoutingLookup($result);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
