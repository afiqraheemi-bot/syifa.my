<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TenantManagement;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerPasswordResetLinkIssuerInterface;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClinicOwnerPasswordResetRequestTest extends TestCase
{
    public function test_reset_link_renders_the_existing_setup_form_and_returns_to_login(): void
    {
        $this->get('/clinic-owner/setup/opaque-token?email=owner%40example.test')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('TenantManagement/Authentication/ClinicOwnerSetup', false)
                ->where('token', 'opaque-token')
                ->where('email', 'owner@example.test')
                ->where('submitUrl', route('clinic-owner.setup.complete', absolute: false))
                ->where('loginUrl', route('login', absolute: false)));
    }

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
