<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Domain\Aggregates\Tenant;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantAdminRoutingLabelException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\TenantAdminRoutingLabelAlreadyAssignedException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleTimestamps;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantAdminRoutingLabelTest extends TestCase
{
    public function test_it_accepts_a_valid_lowercase_ascii_dns_label(): void
    {
        self::assertSame('klinik-syifa9', (new TenantAdminRoutingLabel('klinik-syifa9'))->value);
    }

    #[DataProvider('invalidLabels')]
    public function test_it_rejects_invalid_or_reserved_labels(string $label): void
    {
        $this->expectException(InvalidTenantAdminRoutingLabelException::class);

        new TenantAdminRoutingLabel($label);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLabels(): iterable
    {
        yield 'uppercase is explicitly rejected' => ['Clinic'];
        yield 'too short' => ['ab'];
        yield 'too long' => [str_repeat('a', 64)];
        yield 'leading hyphen' => ['-clinic'];
        yield 'trailing hyphen' => ['clinic-'];
        yield 'consecutive hyphens' => ['clinic--one'];
        yield 'dot or hostname' => ['clinic.app.syifa.my'];
        yield 'space' => ['clinic one'];
        yield 'protocol' => ['https://clinic'];
        yield 'port' => ['clinic:443'];
        yield 'path' => ['clinic/login'];
        yield 'query' => ['clinic?next=login'];
        yield 'userinfo' => ['owner@clinic'];
        foreach (['www', 'app', 'admin', 'api', 'support', 'status', 'assets', 'static'] as $reserved) {
            yield 'reserved '.$reserved => [$reserved];
        }
    }

    public function test_tenant_owns_first_assignment_and_rejects_replacement(): void
    {
        $tenant = $this->tenant();
        $label = new TenantAdminRoutingLabel('clinic-one');
        $tenant->assignAdminRoutingLabel($label);
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel('clinic-one'));

        self::assertSame($label, $tenant->adminRoutingLabel());

        $this->expectException(TenantAdminRoutingLabelAlreadyAssignedException::class);
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel('clinic-two'));
    }

    public function test_label_survives_lifecycle_transitions_and_reconstitution(): void
    {
        $tenant = $this->tenant();
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel('clinic-one'));
        $tenant->establishClinicOwnerAuthority(
            new ClinicOwnerAuthorityId($this->uuid(10)),
            new ClinicOwnerIdentity(
                new ClinicOwnerIdentityId($this->uuid(20)),
                new ClinicOwnerEmail('owner@example.test'),
                new ClinicOwnerName('Clinic Owner'),
            ),
            $this->time('10:01:00'),
        );
        $tenant->activate($this->time('10:02:00'));
        $tenant->suspend($this->time('10:03:00'));
        $tenant->beginOffboarding($this->time('10:04:00'));
        $tenant->anonymize($this->time('10:05:00'));

        self::assertSame('clinic-one', $tenant->adminRoutingLabel()?->value);

        $reconstituted = Tenant::reconstitute(
            $tenant->id,
            TenantLifecycleStatus::Anonymized,
            new TenantLifecycleTimestamps(
                $this->time('10:00:00'),
                $this->time('10:02:00'),
                $this->time('10:03:00'),
                null,
                $this->time('10:04:00'),
                $this->time('10:05:00'),
            ),
            $tenant->clinicOwnerAuthorityHistory(),
            4,
            $tenant->adminRoutingLabel(),
        );

        self::assertSame('clinic-one', $reconstituted->adminRoutingLabel()?->value);
        self::assertSame([], $reconstituted->releaseDomainEvents());
    }

    private function tenant(): Tenant
    {
        return Tenant::provision(new TenantId($this->uuid(1)), $this->time('10:00:00'));
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
