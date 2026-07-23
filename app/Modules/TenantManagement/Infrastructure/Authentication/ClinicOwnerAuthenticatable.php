<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Authentication;

use DateTimeImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * The sole reason an Eloquent model exists in this module (every other
 * Infrastructure class here uses the query builder / Domain repositories
 * directly): Laravel's native Guard/UserProvider/password-broker/
 * email-verification contracts all require an `Authenticatable` object.
 * This model is a thin identity adapter over the existing
 * `clinic_owner_authorities` table (unchanged schema) — it carries no
 * business logic; every business rule (lockout, tenant-scoping,
 * verification) stays inside the `Tenant` aggregate via
 * `ClinicOwnerCredentialVerificationInterface`, which this model never
 * calls — see `ClinicOwnerAuthenticationOrchestrationArchitectureTest`,
 * which locks `AuthenticateClinicOwnerService` to keep calling that
 * Contract directly. Native Guard registration therefore happens only as a
 * side effect of session establishment (`LaravelClinicOwnerSessionStore`),
 * never as the verification path itself.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $clinic_owner_identity_id
 * @property string $email
 * @property string $password_hash
 * @property ?string $remember_token
 */
final class ClinicOwnerAuthenticatable extends Model implements AuthenticatableContract, CanResetPasswordContract, MustVerifyEmailContract
{
    use Authenticatable;
    use CanResetPasswordTrait;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $table = 'clinic_owner_authorities';

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

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
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
