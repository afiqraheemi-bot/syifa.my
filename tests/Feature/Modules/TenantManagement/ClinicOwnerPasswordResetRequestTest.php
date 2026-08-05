<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TenantManagement;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerPasswordResetLinkIssuerInterface;
use Tests\TestCase;

final class ClinicOwnerPasswordResetRequestTest extends TestCase
{
    public function test_forgot_password_request_delegates_without_disclosing_account_ownership(): void
    {
        $issuer = new RecordingClinicOwnerPasswordResetLinkIssuer;
        $this->app->instance(ClinicOwnerPasswordResetLinkIssuerInterface::class, $issuer);

        $this->postJson('/api/v1/password/forgot', ['email' => 'Owner@Example.Test'])
            ->assertOk()
            ->assertExactJson(['data' => ['acknowledged' => true]]);

        self::assertSame('Owner@Example.Test', $issuer->email);
    }

    public function test_forgot_password_request_rejects_invalid_email_without_issuing_a_link(): void
    {
        $issuer = new RecordingClinicOwnerPasswordResetLinkIssuer;
        $this->app->instance(ClinicOwnerPasswordResetLinkIssuerInterface::class, $issuer);

        $this->postJson('/api/v1/password/forgot', ['email' => 'not-an-email'])
            ->assertUnprocessable();

        self::assertNull($issuer->email);
    }
}

final class RecordingClinicOwnerPasswordResetLinkIssuer implements ClinicOwnerPasswordResetLinkIssuerInterface
{
    public ?string $email = null;

    public function issueForEmail(string $email): void
    {
        $this->email = $email;
    }
}
