<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createPlanTables();
        $this->createBillingOptionTables();
        $this->createCapabilityTables();
        $this->createPlanOfferingTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_catalogue_plan_offering_versions');
        Schema::dropIfExists('commercial_catalogue_plan_offerings');
        Schema::dropIfExists('commercial_catalogue_capability_versions');
        Schema::dropIfExists('commercial_catalogue_capabilities');
        Schema::dropIfExists('commercial_catalogue_billing_option_versions');
        Schema::dropIfExists('commercial_catalogue_billing_options');
        Schema::dropIfExists('commercial_catalogue_plan_versions');
        Schema::dropIfExists('commercial_catalogue_plans');
    }

    private function createPlanTables(): void
    {
        Schema::create('commercial_catalogue_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description');
            $table->string('status', 20);
            $table->unsignedInteger('display_order');
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_last_changed_at', 6);
            $table->unsignedInteger('version');
            $table->timestampsTz(6);
        });

        Schema::create('commercial_catalogue_plan_versions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->unsignedInteger('version');
            $table->string('code', 50);
            $table->string('name', 100);
            $table->text('description');
            $table->string('status', 20);
            $table->unsignedInteger('display_order');
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_last_changed_at', 6);
            $table->timestampsTz(6);
            $table->primary(['id', 'version']);
            $table->index('code');
        });
    }

    private function createBillingOptionTables(): void
    {
        Schema::create('commercial_catalogue_billing_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('availability', 20);
            $table->string('recurrence_classification', 20);
            $table->string('interval_unit', 20)->nullable();
            $table->unsignedTinyInteger('interval_count')->nullable();
            $table->date('effective_start');
            $table->date('effective_end')->nullable();
            $table->unsignedInteger('display_order');
            $table->unsignedInteger('version');
            $table->timestampsTz(6);
        });

        Schema::create('commercial_catalogue_billing_option_versions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->unsignedInteger('version');
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('availability', 20);
            $table->string('recurrence_classification', 20);
            $table->string('interval_unit', 20)->nullable();
            $table->unsignedTinyInteger('interval_count')->nullable();
            $table->date('effective_start');
            $table->date('effective_end')->nullable();
            $table->unsignedInteger('display_order');
            $table->timestampsTz(6);
            $table->primary(['id', 'version']);
            $table->index('code');
            $table->index(['effective_start', 'effective_end', 'availability']);
        });
    }

    private function createCapabilityTables(): void
    {
        Schema::create('commercial_catalogue_capabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 80)->unique();
            $table->string('name', 100);
            $table->text('description');
            $table->text('commercial_meaning');
            $table->string('status', 20);
            $table->unsignedInteger('version');
            $table->timestampsTz(6);
        });

        Schema::create('commercial_catalogue_capability_versions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->unsignedInteger('version');
            $table->string('key', 80);
            $table->string('name', 100);
            $table->text('description');
            $table->text('commercial_meaning');
            $table->string('status', 20);
            $table->timestampsTz(6);
            $table->primary(['id', 'version']);
            $table->index('key');
        });
    }

    private function createPlanOfferingTables(): void
    {
        Schema::create('commercial_catalogue_plan_offerings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('commercial_catalogue_plans')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('billing_option_id')->constrained('commercial_catalogue_billing_options')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('status', 20);
            $table->date('effective_start');
            $table->date('effective_end')->nullable();
            $table->string('configuration_version', 100);
            $table->string('capability_configuration_reference', 100);
            $table->unsignedInteger('display_order');
            $table->unsignedInteger('version');
            $table->timestampsTz(6);
            $table->index(['plan_id', 'effective_start']);
            $table->index(['billing_option_id', 'effective_start']);
        });

        Schema::create('commercial_catalogue_plan_offering_versions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->unsignedInteger('version');
            $table->foreignUuid('plan_id')->constrained('commercial_catalogue_plans')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('billing_option_id')->constrained('commercial_catalogue_billing_options')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('status', 20);
            $table->date('effective_start');
            $table->date('effective_end')->nullable();
            $table->string('configuration_version', 100);
            $table->string('capability_configuration_reference', 100);
            $table->unsignedInteger('display_order');
            $table->timestampsTz(6);
            $table->primary(['id', 'version']);
            $table->index('plan_id');
            $table->index(['effective_start', 'effective_end', 'status']);
        });
    }
};
