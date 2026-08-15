<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Provisioning\Application;

use App\Modules\ClinicRegistration\Contracts\Checkout\CompleteLocalDemoAcquisitionInterface;
use App\Support\Provisioning\Application\ActivateApprovedFreeTrialService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivateApprovedFreeTrialServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clinic_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('platform_identity_id');
            $table->string('status', 32);
            $table->string('selected_plan_offering_reference', 120)->nullable();
            $table->string('registration_correlation_reference', 120)->unique();
            $table->unsignedBigInteger('version');
            $table->timestampsTz();
        });
        Schema::create('commercial_catalogue_plan_offerings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32);
            $table->unsignedInteger('amount_minor');
            $table->string('capability_configuration_reference', 120);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clinic_registrations');
        Schema::dropIfExists('commercial_catalogue_plan_offerings');

        parent::tearDown();
    }

    #[Test]
    public function it_does_not_activate_or_crash_when_the_selected_plan_offering_reference_is_not_a_uuid(): void
    {
        // Reproduces a real incident: nothing in the registration draft
        // validation requires selected_plan_offering_reference to be a UUID
        // (it is just ['nullable', 'string', 'max:120']), so an approved
        // registration can carry an arbitrary string here. Passing that
        // straight into a `where('id', ...)` lookup against a uuid column
        // used to throw an unhandled QueryException — after the registration
        // decision had already committed — leaving Super Admin with an
        // unexplained 500 for an approval that actually succeeded.
        $registrationId = (string) Str::uuid();
        DB::table('clinic_registrations')->insert([
            'id' => $registrationId,
            'platform_identity_id' => (string) Str::uuid(),
            'status' => 'approved',
            'selected_plan_offering_reference' => 'not-a-uuid-reference',
            'registration_correlation_reference' => (string) Str::uuid(),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activation = new class implements CompleteLocalDemoAcquisitionInterface
        {
            public bool $called = false;

            public function execute(string $trackingCredential, string $correlationId): void
            {
                $this->called = true;
            }
        };

        $service = new ActivateApprovedFreeTrialService(DB::connection(), $activation);

        $result = $service->execute($registrationId, (string) Str::uuid());

        self::assertFalse($result);
        self::assertFalse($activation->called);
    }

    #[Test]
    public function it_activates_a_genuine_free_trial_offering(): void
    {
        $registrationId = (string) Str::uuid();
        $offeringId = (string) Str::uuid();
        DB::table('commercial_catalogue_plan_offerings')->insert([
            'id' => $offeringId,
            'status' => 'active',
            'amount_minor' => 0,
            'capability_configuration_reference' => 'package:syifa-trial',
        ]);
        DB::table('clinic_registrations')->insert([
            'id' => $registrationId,
            'platform_identity_id' => (string) Str::uuid(),
            'status' => 'approved',
            'selected_plan_offering_reference' => $offeringId,
            'registration_correlation_reference' => (string) Str::uuid(),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activation = new class implements CompleteLocalDemoAcquisitionInterface
        {
            public bool $called = false;

            public function execute(string $trackingCredential, string $correlationId): void
            {
                $this->called = true;
            }
        };

        $service = new ActivateApprovedFreeTrialService(DB::connection(), $activation);

        $result = $service->execute($registrationId, (string) Str::uuid());

        self::assertTrue($result);
        self::assertTrue($activation->called);
    }
}
