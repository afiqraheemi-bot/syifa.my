<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialPasswordWriterInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use DateTimeImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use SensitiveParameter;
use Tests\TestCase;

final class PlatformPasswordResetEndpointsTest extends TestCase
{
    private const string IDENTITY_ID = '00000000-0000-4000-8000-000000000777';

    private const string EMAIL = 'designer@example.test';

    private PasswordResetTrackingCredentialLookup $credentials;

    private PasswordResetTrackingPasswordWriter $writer;

    private PasswordResetTrackingAuditEntryRecorder $auditRecorder;

    private Migration $tokensMigration;

    protected function setUp(): void
    {
        parent::setUp();

        // The password broker persists reset tokens directly via the "database"
        // driver (Illuminate\Auth\Passwords\DatabaseTokenRepository) — a real,
        // portable table, unlike credential verification itself which stays
        // fully behind the Contracts fakes below.
        Schema::dropIfExists('platform_identity_password_reset_tokens');
        $migration = require base_path('database/migrations/platform_administration/2026_08_21_000002_create_platform_identity_password_reset_tokens_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->tokensMigration = $migration;
        $this->tokensMigration->up();

        $this->credentials = new PasswordResetTrackingCredentialLookup(new PlatformWorkforceCredentialData(
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
        ));
        $this->writer = new PasswordResetTrackingPasswordWriter;
        $this->auditRecorder = new PasswordResetTrackingAuditEntryRecorder;

        $this->app->instance(PlatformWorkforceCredentialLookupInterface::class, $this->credentials);
        $this->app->instance(PlatformWorkforceCredentialPasswordWriterInterface::class, $this->writer);
        $this->app->instance(AuditEntryRecorderInterface::class, $this->auditRecorder);
    }

    protected function tearDown(): void
    {
        $this->tokensMigration->down();
        parent::tearDown();
    }

    public function test_forgot_password_with_a_known_email_sends_a_reset_link_and_acknowledges(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/platform/password/forgot', ['email' => self::EMAIL]);

        $response->assertOk()->assertJsonPath('data.acknowledged', true);
        Notification::assertSentTo($this->notifiable(), ResetPassword::class);
        self::assertSame('platform.authentication.password_reset_requested', $this->auditRecorder->entries[0]->action);
        self::assertSame(AuditOutcomeType::Succeeded->value, $this->auditRecorder->entries[0]->outcome->outcome);
    }

    public function test_forgot_password_with_an_unknown_email_returns_the_same_acknowledgement(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/platform/password/forgot', ['email' => 'nobody@example.test']);

        $response->assertOk()->assertJsonPath('data.acknowledged', true);
        self::assertSame(AuditOutcomeType::Failed->value, $this->auditRecorder->entries[0]->outcome->outcome);
    }

    public function test_reset_password_with_a_valid_token_updates_the_password_and_reports_success(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/platform/password/forgot', ['email' => self::EMAIL]);

        $token = null;
        Notification::assertSentTo(
            $this->notifiable(),
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );
        self::assertIsString($token);

        $response = $this->postJson('/api/v1/platform/password/reset', [
            'email' => self::EMAIL,
            'token' => $token,
            'password' => 'a-genuinely-long-new-password',
            'password_confirmation' => 'a-genuinely-long-new-password',
        ]);

        $response->assertOk()->assertJsonPath('data.reset', true);
        self::assertSame(self::IDENTITY_ID, $this->writer->updatedIdentityId);
        self::assertSame('a-genuinely-long-new-password', $this->writer->updatedPassword);
    }

    public function test_reset_password_with_an_invalid_token_is_rejected_and_never_writes_a_password(): void
    {
        $response = $this->postJson('/api/v1/platform/password/reset', [
            'email' => self::EMAIL,
            'token' => 'not-a-real-token',
            'password' => 'a-genuinely-long-new-password',
            'password_confirmation' => 'a-genuinely-long-new-password',
        ]);

        $response->assertStatus(422)->assertJsonPath('type', 'password_reset_failed');
        self::assertNull($this->writer->updatedIdentityId);
    }

    private function notifiable(): PlatformIdentityAuthenticatable
    {
        return (new PlatformIdentityAuthenticatable)->forceFill([
            'platform_identity_id' => self::IDENTITY_ID,
            'normalized_email' => self::EMAIL,
        ]);
    }
}

final class PasswordResetTrackingCredentialLookup implements PlatformWorkforceCredentialLookupInterface
{
    public function __construct(public PlatformWorkforceCredentialData $credential) {}

    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
    {
        return strtolower($email) === $this->credential->normalizedEmail ? $this->credential : null;
    }
}

final class PasswordResetTrackingPasswordWriter implements PlatformWorkforceCredentialPasswordWriterInterface
{
    public ?string $updatedIdentityId = null;

    public ?string $updatedPassword = null;

    public function updatePassword(
        string $platformIdentityId,
        #[SensitiveParameter] string $newPlainPassword,
        DateTimeImmutable $updatedAt,
    ): void {
        $this->updatedIdentityId = $platformIdentityId;
        $this->updatedPassword = $newPlainPassword;
    }
}

final class PasswordResetTrackingAuditEntryRecorder implements AuditEntryRecorderInterface
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
