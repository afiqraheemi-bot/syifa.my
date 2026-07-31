<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaChallengeData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaChallengeInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationResult;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PlatformSessionEndpointsTest extends TestCase
{
    private const IDENTITY_ID = '00000000-0000-4000-8000-000000000555';

    private MutableCredentialVerification $verification;

    private MutablePlatformIdentityLookup $identities;

    private MutablePlatformWorkforceCredentialLookup $credentials;

    private MutablePlatformAuditEntryRecorder $auditRecorder;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
        if (! Schema::hasTable('platform_workforce_credentials')) {
            Schema::create('platform_workforce_credentials', function (Blueprint $table): void {
                $table->uuid('platform_identity_id')->primary();
                $table->string('normalized_email');
                $table->string('password_hash');
                $table->string('email_verification_status');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('account_status');
                $table->unsignedInteger('failed_attempt_count')->default(0);
                $table->timestamp('lockout_until')->nullable();
                $table->string('name');
                $table->string('role');
                $table->rememberToken();
                $table->unsignedBigInteger('version');
                $table->timestamps();
            });
        }
        DB::table('platform_workforce_credentials')->updateOrInsert(
            ['platform_identity_id' => self::IDENTITY_ID],
            [
                'normalized_email' => 'designer@example.test',
                'password_hash' => Hash::make('correct horse battery staple'),
                'email_verification_status' => 'verified',
                'email_verified_at' => now(),
                'account_status' => 'active',
                'failed_attempt_count' => 0,
                'name' => 'Website Designer',
                'role' => 'website_designer',
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $this->verification = new MutableCredentialVerification(true, self::IDENTITY_ID);
        $this->identities = new MutablePlatformIdentityLookup(new PlatformIdentityData(
            self::IDENTITY_ID,
            'designer@example.test',
            'Website Designer',
            'website_designer',
            'active',
        ));
        $this->credentials = new MutablePlatformWorkforceCredentialLookup(new PlatformWorkforceCredentialData(
            self::IDENTITY_ID,
            'designer@example.test',
            true,
            new DateTimeImmutable('2026-07-19T09:00:00Z'),
            'active',
            0,
            null,
            1,
            new DateTimeImmutable('2026-07-19T09:00:00Z'),
            new DateTimeImmutable('2026-07-19T09:15:00Z'),
        ));
        $this->auditRecorder = new MutablePlatformAuditEntryRecorder;

        $this->app->instance(CredentialVerificationInterface::class, $this->verification);
        $this->app->instance(PlatformIdentityLookupInterface::class, $this->identities);
        $this->app->instance(PlatformWorkforceCredentialLookupInterface::class, $this->credentials);
        $this->app->instance(AuditEntryRecorderInterface::class, $this->auditRecorder);
        $this->app->instance(
            PlatformMfaChallengeInterface::class,
            new CompletingPlatformMfaChallenge($this->app->make(PlatformSessionStoreInterface::class)),
        );
        $this->app->instance(
            WebsiteDesignerDashboardReadInterface::class,
            new EmptyWebsiteDesignerDashboardRead,
        );
    }

    public function test_successful_login_session_regeneration_principal_resolution_and_logout_work_end_to_end(): void
    {
        $startingSessionId = session()->getId();

        $login = $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions', [
            'email' => 'designer@example.test',
            'password' => 'correct horse battery staple',
        ]);

        $login->assertStatus(202)
            ->assertJsonPath('data.authenticated', false)
            ->assertJsonPath('data.state', 'mfa_required')
            ->assertJsonStructure(['data' => ['csrf_token']]);

        $this->getJson('https://clinic.app.syifa.my/api/v1/platform/sessions/current')
            ->assertUnauthorized()
            ->assertJsonPath('type', 'session_invalid');

        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions/mfa', [
            'code' => '123456',
        ])->assertCreated()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.principal.platform_identity_id', self::IDENTITY_ID);

        self::assertNotSame($startingSessionId, session()->getId());
        $this->getJson('https://clinic.app.syifa.my/api/v1/platform/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.principal.platform_identity_id', self::IDENTITY_ID)
            ->assertJsonPath('data.principal.role', 'website_designer');

        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'same-address@example.test',
            'password' => 'correct horse battery staple',
        ])->assertStatus(409)
            ->assertJsonPath('type', 'already_authenticated');

        $this->get('https://clinic.app.syifa.my/dashboard')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Dashboard/WebsiteDesignerDashboardOverview', false)
                    ->where('identityName', 'Website Designer')
                    ->has('summaries', 6)
                    ->where('recentAssignments', []),
            );

        $this->deleteJson('https://clinic.app.syifa.my/api/v1/platform/sessions/current')->assertNoContent();
        $this->deleteJson('https://clinic.app.syifa.my/api/v1/platform/sessions/current')->assertNoContent();

        $this->getJson('https://clinic.app.syifa.my/api/v1/platform/sessions/current')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'session_invalid');

    }

    public function test_invalid_password_and_locked_account_fail_closed(): void
    {
        $this->verification->verified = false;
        $this->verification->locked = false;

        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions', [
            'email' => 'designer@example.test',
            'password' => 'wrong',
        ])->assertUnauthorized()
            ->assertJsonPath('type', 'authentication_failed');

        $this->verification->verified = true;
        $this->verification->platformIdentityId = self::IDENTITY_ID;
        $this->verification->locked = true;
        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions', [
            'email' => 'designer@example.test',
            'password' => 'wrong-again',
        ])->assertUnauthorized()
            ->assertJsonPath('type', 'authentication_failed');
    }

    public function test_inactive_account_and_middleware_validation_fail_closed(): void
    {
        $this->identities->identity = new PlatformIdentityData(
            self::IDENTITY_ID,
            'designer@example.test',
            'Website Designer',
            'website_designer',
            'suspended',
        );

        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions', [
            'email' => 'designer@example.test',
            'password' => 'correct horse battery staple',
        ])->assertUnauthorized()
            ->assertJsonPath('type', 'authentication_failed');

        $this->identities->identity = new PlatformIdentityData(
            self::IDENTITY_ID,
            'designer@example.test',
            'Website Designer',
            'website_designer',
            'active',
        );
        $this->verification->verified = true;

        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions', [
            'email' => 'designer@example.test',
            'password' => 'correct horse battery staple',
        ])->assertStatus(202);
        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions/mfa', [
            'code' => '123456',
        ])->assertCreated();

        $this->identities->identity = new PlatformIdentityData(
            self::IDENTITY_ID,
            'designer@example.test',
            'Website Designer',
            'website_designer',
            'revoked',
        );

        $this->getJson('https://clinic.app.syifa.my/api/v1/platform/sessions/current')
            ->assertUnauthorized()
            ->assertJsonPath('type', 'session_invalid');
    }

    public function test_the_platform_session_routes_exist_and_use_web_middleware(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_contains($route->uri(), 'api/v1/platform/sessions'))
            ->map(static fn ($route): array => [$route->methods(), $route->uri(), $route->gatherMiddleware()])
            ->values()
            ->all();

        self::assertCount(4, $routes);
        self::assertSame('api/v1/platform/sessions', $routes[0][1]);
        self::assertSame('api/v1/platform/sessions/mfa', $routes[1][1]);
        self::assertSame('api/v1/platform/sessions/current', $routes[2][1]);
        self::assertSame('api/v1/platform/sessions/current', $routes[3][1]);
        self::assertContains('web', $routes[0][2]);
        self::assertContains('web', $routes[1][2]);
        self::assertContains('web', $routes[2][2]);
        self::assertContains('web', $routes[3][2]);
    }
}

final class CompletingPlatformMfaChallenge implements PlatformMfaChallengeInterface
{
    private ?PlatformPrincipal $principal = null;

    public function __construct(private readonly PlatformSessionStoreInterface $sessions) {}

    public function begin(
        PlatformPrincipal $principal,
        string $normalizedEmail,
        bool $remember,
        DateTimeImmutable $at,
    ): PlatformMfaChallengeData {
        $this->principal = $principal;

        return new PlatformMfaChallengeData('mfa_required');
    }

    public function complete(string $code, DateTimeImmutable $at): ?PlatformPrincipal
    {
        if ($code !== '123456' || ! $this->principal instanceof PlatformPrincipal) {
            return null;
        }

        $this->sessions->establish($this->principal, $at);

        return $this->principal;
    }
}

final readonly class EmptyWebsiteDesignerDashboardRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        return new WebsiteDesignerDashboardData(0, 0, 0, 0, 0, 0, []);
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        return null;
    }
}

final class MutableCredentialVerification implements CredentialVerificationInterface
{
    public function __construct(
        public bool $verified,
        public ?string $platformIdentityId,
    ) {}

    public bool $locked = false;

    public function verify(
        string $email,
        #[\SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): CredentialVerificationResult {
        return new CredentialVerificationResult($this->verified && ! $this->locked, $this->platformIdentityId);
    }
}

final class MutablePlatformIdentityLookup implements PlatformIdentityLookupInterface
{
    public function __construct(public PlatformIdentityData $identity) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        return $this->identity->id === $platformIdentityId ? $this->identity : null;
    }
}

final class MutablePlatformWorkforceCredentialLookup implements PlatformWorkforceCredentialLookupInterface
{
    public function __construct(public PlatformWorkforceCredentialData $credential) {}

    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
    {
        return strtolower($email) === $this->credential->normalizedEmail ? $this->credential : null;
    }
}

final class MutablePlatformAuditEntryRecorder implements AuditEntryRecorderInterface
{
    /** @var list<AuditEntryData> */
    public array $entries = [];

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}
