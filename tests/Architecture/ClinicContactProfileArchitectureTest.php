<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ClinicContactProfileArchitectureTest extends TestCase
{
    public function test_profile_is_an_internal_value_object_modified_only_by_clinic(): void
    {
        $profile = $this->source('app/Modules/WebsiteBuilder/Domain/ValueObjects/ClinicContactProfile.php');
        $clinic = $this->source('app/Modules/WebsiteBuilder/Domain/Clinic.php');

        self::assertStringContainsString('final readonly class ClinicContactProfile', $profile);
        self::assertStringContainsString('updateContactProfile(ClinicContactProfile $profile', $clinic);
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Contracts/Repositories/ClinicContactProfileRepositoryInterface.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Domain/ClinicContactProfile.php');
    }

    public function test_profile_introduces_no_forbidden_delivery_or_cross_context_dependencies(): void
    {
        foreach ($this->phpFiles('app/Modules/WebsiteBuilder/Domain', 'app/Modules/WebsiteBuilder/Application/ClinicContact') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            foreach ([
                'Infrastructure\\', 'Illuminate\\', 'Modules\\Booking\\', 'Modules\\SubscriptionBilling\\',
                'Google', 'https://wa.me/', 'whatsapp://', 'Tracking', 'Analytics', 'Storage', 'Http\\Controllers',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_persistence_remains_inside_clinic_repository_and_uses_normalized_columns(): void
    {
        $migration = $this->source('database/migrations/website_builder/2026_08_17_000001_create_clinic_contact_profiles.php');
        self::assertStringContainsString("Schema::create('clinic_contact_profiles'", $migration);
        self::assertStringNotContainsString('->json(', $migration);
        self::assertStringNotContainsString('provider_url', $migration);
        self::assertStringContainsString('clinic_contact_profiles_clinic_tenant_foreign', $migration);
    }

    public function test_no_new_aggregate_root_or_bounded_context_is_introduced(): void
    {
        $profile = $this->source('app/Modules/WebsiteBuilder/Domain/ValueObjects/ClinicContactProfile.php');
        self::assertStringNotContainsString('AggregateRoot', $profile);
        self::assertDirectoryDoesNotExist($this->root().'/app/Modules/ClinicContact');
    }

    /** @return list<string> */
    private function phpFiles(string ...$directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root().'/'.$directory));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function source(string $relative): string
    {
        $source = file_get_contents($this->root().'/'.$relative);
        self::assertIsString($source);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
