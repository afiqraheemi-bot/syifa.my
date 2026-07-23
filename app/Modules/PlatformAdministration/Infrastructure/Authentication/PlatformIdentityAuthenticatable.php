<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Authentication;

use DateTimeImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * The sole reason an Eloquent model exists in this module (every other
 * Infrastructure class here uses the query builder directly): Laravel's
 * native Guard/UserProvider/password-broker/email-verification contracts
 * all require an `Authenticatable` object. This model is a thin identity
 * adapter over the existing `platform_workforce_credentials` table (ADR-
 * governed schema, unchanged) — it carries no business logic; every
 * business rule (lockout, role gating, audit) stays in
 * `CredentialVerificationInterface` and its existing implementation.
 *
 * @property string $platform_identity_id
 * @property string $normalized_email
 * @property string $password_hash
 * @property string $email_verification_status
 * @property ?Carbon $email_verified_at
 * @property string $account_status
 * @property string $name
 * @property string $role
 * @property ?string $remember_token
 */
final class PlatformIdentityAuthenticatable extends Model implements AuthenticatableContract, CanResetPasswordContract, MustVerifyEmailContract
{
    use Authenticatable;
    use CanResetPasswordTrait;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $table = 'platform_workforce_credentials';

    protected $primaryKey = 'platform_identity_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'lockout_until' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /** Notifications/broker route to the normalized email — this table has no `email` column. */
    public function getEmailForPasswordReset(): string
    {
        return $this->normalized_email;
    }

    public function getEmailForVerification(): string
    {
        return $this->normalized_email;
    }

    public function routeNotificationForMail(): string
    {
        return $this->normalized_email;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verification_status === 'verified' && $this->email_verified_at !== null;
    }

    /** Keeps `email_verification_status` and `email_verified_at` coherent — the table's own CHECK constraint requires it. */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verification_status' => 'verified',
            'email_verified_at' => new DateTimeImmutable,
        ])->save();
    }
}
