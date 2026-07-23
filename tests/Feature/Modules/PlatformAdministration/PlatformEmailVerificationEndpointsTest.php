<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialStateWriterInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use DateTimeImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class PlatformEmailVerificationEndpointsTest extends TestCase
{
    private const string IDENTITY_ID = '00000000-0000-4000-8000-000000000888';

    private const string EMAIL = 'designer@example.test';

    private EmailVerificationTrackingIdentityLookup $identities;

    private EmailVerificationTrackingCredentialLookup $credentials;

    private EmailVerificationTrackingStateWriter $writer;

    private EmailVerificationTrackingAuditEntryRecorder $auditRecorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identities = new EmailVerificationTrackingIdentityLookup(new PlatformIdentityData(
            self::IDENTITY_ID,
            self::EMAIL,
            'Website Designer',
            'website_designer',
            'active',
        ));
        $this->credentials = new EmailVerificationTrackingCredentialLookup(new PlatformWorkforceCredentialData(
            self::IDENTITY_ID,
            self::EMAIL,
            false,
            null,
            'active',
            0,
            null,
            1,
            new DateTimeImmutable('2026-07-19T09:00:00Z'),
            new DateTimeImmutable('2026-07-19T09:15:00Z'),
        ));
        $this->writer = new EmailVerificationTrackingStateWriter;
        $this->auditRecorder = new EmailVerificationTrackingAuditEntryRecorder;

        $this->app->instance(PlatformIdentityLookupInterface::class, $this->identities);
        $this->app->instance(PlatformWorkforceCredentialLookupInterface::class, $this->credentials);
        $this->app->instance(PlatformWorkforceCredentialStateWriterInterface::class, $this->writer);
        $this->app->instance(AuditEntryRecorderInterface::class, $this->auditRecorder);
    }

    public function test_verify_with_a_genuine_signed_link_marks_the_credential_verified(): void
    {
        $url = URL::temporarySignedRoute('platform.email.verify', now()->addMinutes(60), [
            'id' => self::IDENTITY_ID,
            'hash' => sha1(self::EMAIL),
        ]);

        $response = $this->getJson($url);

        $response->assertOk()->assertJsonPath('data.verified', true);
        self::assertTrue($this->writer->savedState?->emailVerified);
        self::assertSame(
            'platform.authentication.email_verified',
            $this->auditRecorder->entries[0]->action,
        );
        self::assertSame(AuditOutcomeType::Succeeded->value, $this->auditRecorder->entries[0]->outcome->outcome);
    }

    public function test_verify_with_a_tampered_signature_is_rejected_by_the_signed_middleware_before_reaching_the_controller(): void
    {
        $url = URL::temporarySignedRoute('platform.email.verify', now()->addMinutes(60), [
            'id' => self::IDENTITY_ID,
            'hash' => sha1(self::EMAIL),
        ]);

        $response = $this->getJson($url.'&tampered=1');

        $response->assertForbidden();
        self::assertNull($this->writer->savedState);
    }

    public function test_verify_with_a_valid_signature_but_mismatched_hash_fails_closed(): void
    {
        $url = URL::temporarySignedRoute('platform.email.verify', now()->addMinutes(60), [
            'id' => self::IDENTITY_ID,
            'hash' => sha1('someone-else@example.test'),
        ]);

        $response = $this->getJson($url);

        $response->assertStatus(422)->assertJsonPath('type', 'email_verification_failed');
        self::assertNull($this->writer->savedState);
        self::assertSame(AuditOutcomeType::Failed->value, $this->auditRecorder->entries[0]->outcome->outcome);
    }

    public function test_resend_without_an_authenticated_session_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/platform/email/verification-notification');

        $response->assertStatus(401)->assertJsonPath('type', 'session_invalid');
    }

    public function test_resend_with_an_authenticated_unverified_identity_sends_the_notification(): void
    {
        Notification::fake();
        $user = (new PlatformIdentityAuthenticatable)->forceFill([
            'platform_identity_id' => self::IDENTITY_ID,
            'normalized_email' => self::EMAIL,
            'email_verification_status' => 'unverified',
            'email_verified_at' => null,
        ]);
        $this->app->make(AuthFactory::class)->guard('platform_identity')->setUser($user);

        $response = $this->postJson('/api/v1/platform/email/verification-notification');

        $response->assertOk()->assertJsonPath('data.sent', true);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}

final class EmailVerificationTrackingIdentityLookup implements PlatformIdentityLookupInterface
{
    public function __construct(public PlatformIdentityData $identity) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        return $this->identity->id === $platformIdentityId ? $this->identity : null;
    }
}

final class EmailVerificationTrackingCredentialLookup implements PlatformWorkforceCredentialLookupInterface
{
    public function __construct(public PlatformWorkforceCredentialData $credential) {}

    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
    {
        return strtolower($email) === $this->credential->normalizedEmail ? $this->credential : null;
    }
}

final class EmailVerificationTrackingStateWriter implements PlatformWorkforceCredentialStateWriterInterface
{
    public ?PlatformWorkforceCredentialData $savedState = null;

    public function saveState(
        PlatformWorkforceCredentialData $credential,
        DateTimeImmutable $updatedAt,
    ): PlatformWorkforceCredentialData {
        $this->savedState = $credential;

        return $credential;
    }
}

final class EmailVerificationTrackingAuditEntryRecorder implements AuditEntryRecorderInterface
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
