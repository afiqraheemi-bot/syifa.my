<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
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
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use DateTimeImmutable;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\Route;
use SensitiveParameter;
use Tests\TestCase;

final class PlatformPasswordConfirmationEndpointsTest extends TestCase
{
    private const string IDENTITY_ID = '00000000-0000-4000-8000-000000000999';

    private const string EMAIL = 'designer@example.test';

    private const string PASSWORD = 'correct horse battery staple';

    private PasswordConfirmationTrackingCredentialVerification $verification;

    private PasswordConfirmationTrackingAuditEntryRecorder $auditRecorder;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');

        $this->verification = new PasswordConfirmationTrackingCredentialVerification(self::IDENTITY_ID);
        $this->auditRecorder = new PasswordConfirmationTrackingAuditEntryRecorder;

        $this->app->instance(CredentialVerificationInterface::class, $this->verification);
        $this->app->instance(PlatformIdentityLookupInterface::class, new PasswordConfirmationFixedIdentityLookup(
            new PlatformIdentityData(self::IDENTITY_ID, self::EMAIL, 'Website Designer', 'website_designer', 'active'),
        ));
        $this->app->instance(PlatformWorkforceCredentialLookupInterface::class, new PasswordConfirmationFixedCredentialLookup(
            new PlatformWorkforceCredentialData(
                self::IDENTITY_ID,
                self::EMAIL,
                true,
                new DateTimeImmutable('2026-07-19T09:00:00Z'),
                'active',
                0,
                null,
                1,
                new DateTimeImmutable('2026-07-19T09:00:00Z'),
                new DateTimeImmutable('2026-07-19T09:15:00Z'),
            ),
        ));
        $this->app->instance(AuditEntryRecorderInterface::class, $this->auditRecorder);

        Route::middleware(['web', RequirePassword::using(null, 10800)])
            ->get('/password-confirmation-test/protected', static fn () => response()->json(['ok' => true]));

        $this->app->make(AuthFactory::class)->guard('platform_identity')->setUser(
            (new PlatformIdentityAuthenticatable)->forceFill([
                'platform_identity_id' => self::IDENTITY_ID,
                'normalized_email' => self::EMAIL,
            ]),
        );
    }

    public function test_confirming_the_correct_password_unlocks_a_password_confirm_protected_route(): void
    {
        $this->getJson('/password-confirmation-test/protected')->assertStatus(423);

        $this->postJson('/api/v1/platform/password/confirm', ['password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true);

        $this->getJson('/password-confirmation-test/protected')->assertOk();

        self::assertSame(AuditOutcomeType::Succeeded->value, $this->auditRecorder->entries[0]->outcome->outcome);
    }

    public function test_confirming_the_wrong_password_never_unlocks_the_route(): void
    {
        $this->verification->verified = false;

        $this->postJson('/api/v1/platform/password/confirm', ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'password_confirmation_failed');

        $this->getJson('/password-confirmation-test/protected')->assertStatus(423);
        self::assertSame(AuditOutcomeType::Failed->value, $this->auditRecorder->entries[0]->outcome->outcome);
    }
}

final class PasswordConfirmationTrackingCredentialVerification implements CredentialVerificationInterface
{
    public bool $verified = true;

    public function __construct(public string $identityId) {}

    public function verify(
        string $email,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): CredentialVerificationResult {
        return new CredentialVerificationResult($this->verified, $this->verified ? $this->identityId : null);
    }
}

final class PasswordConfirmationFixedIdentityLookup implements PlatformIdentityLookupInterface
{
    public function __construct(private PlatformIdentityData $identity) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        return $this->identity->id === $platformIdentityId ? $this->identity : null;
    }
}

final class PasswordConfirmationFixedCredentialLookup implements PlatformWorkforceCredentialLookupInterface
{
    public function __construct(private PlatformWorkforceCredentialData $credential) {}

    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
    {
        return strtolower($email) === $this->credential->normalizedEmail ? $this->credential : null;
    }
}

final class PasswordConfirmationTrackingAuditEntryRecorder implements AuditEntryRecorderInterface
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
