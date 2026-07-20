<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class CommercialCatalogueApplicationArchitectureTest extends TestCase
{
    /** @var list<string> */
    private const FILESYSTEM_MUTATOR_TOKENS = ['mkdir(', 'rmdir(', 'unlink(', 'rename(', 'copy(', 'file_put_contents(', 'touch('];

    public function test_exactly_thirty_use_case_services_exist_and_the_validation_service_is_absent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue';

        self::assertSame(
            [
                'ActivateCapabilityDefinitionService.php',
                'ActivatePlanOfferingService.php',
                'ActivatePlanService.php',
                'ComputeSubscriptionEntitlementService.php',
                'CreateBillingOptionService.php',
                'CreateCapabilityDefinitionService.php',
                'CreatePlanOfferingService.php',
                'CreatePlanService.php',
                'DeprecateCapabilityDefinitionService.php',
                'GetBillingOptionService.php',
                'GetCapabilityDefinitionService.php',
                'GetPlanOfferingService.php',
                'GetPlanService.php',
                'GrandfatherPlanOfferingService.php',
                'GrandfatherPlanService.php',
                'ListAvailableSubscriptionOfferingsService.php',
                'ListBillingOptionsService.php',
                'ListCapabilityDefinitionsService.php',
                'ListPlanOfferingsService.php',
                'ListPlansService.php',
                'MakePlanOfferingUnavailableService.php',
                'MakePlanUnavailableService.php',
                'ResolveSubscriptionOfferingService.php',
                'RetireCapabilityDefinitionService.php',
                'RetirePlanOfferingService.php',
                'RetirePlanService.php',
                'UpdateBillingOptionService.php',
                'UpdateCapabilityDefinitionService.php',
                'UpdatePlanDetailsService.php',
                'UpdatePlanOfferingService.php',
            ],
            $this->phpServiceBasenamesIn($root),
        );

        self::assertDirectoryDoesNotExist($root.'/Queries');
        self::assertFileDoesNotExist($root.'/ValidateOfferingSelectionService.php');
    }

    public function test_identifier_generator_exists_in_the_approved_namespace_and_is_stateless(): void
    {
        $class = 'App\\Modules\\SubscriptionBilling\\Application\\CommercialCatalogue\\CommercialCatalogueIdentifierGenerator';

        self::assertTrue(class_exists($class));
        $reflection = new ReflectionClass($class);

        self::assertSame('App\\Modules\\SubscriptionBilling\\Application\\CommercialCatalogue', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        self::assertSame([], $reflection->getProperties());
        self::assertStringContainsString('/Application/CommercialCatalogue/CommercialCatalogueIdentifierGenerator.php', $reflection->getFileName() ?: '');
    }

    public function test_application_layer_has_no_shared_common_helpers_or_support_folders(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application';

        foreach (['Common', 'Shared', 'Helpers', 'Misc', 'Support', 'Utilities'] as $folder) {
            self::assertDirectoryDoesNotExist($root.'/'.$folder);
        }
    }

    public function test_create_services_do_not_contain_private_uuid_generators(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue';

        foreach ([
            'CreatePlanService.php',
            'CreateBillingOptionService.php',
            'CreatePlanOfferingService.php',
            'CreateCapabilityDefinitionService.php',
        ] as $file) {
            $contents = file_get_contents($root.'/'.$file);
            self::assertIsString($contents, $file);
            self::assertStringNotContainsString('private static function uuid4()', $contents, $file);
            self::assertStringNotContainsString('random_bytes(', $contents, $file);
        }
    }

    public function test_application_services_remain_framework_free_and_do_not_introduce_persistence_or_http_behaviour(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            if (basename($file) === 'CommercialCatalogueIdentifierGenerator.php') {
                self::assertStringContainsString('random_bytes(', $contents, $file);

                continue;
            }

            foreach ($this->applicationForbiddenTokens() as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }

            self::assertStringNotContainsString('random_bytes(', $contents, $file);
        }
    }

    public function test_entitlement_boundary_is_still_orchestrated_only_through_the_approved_computation_interface(): void
    {
        $file = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue/ComputeSubscriptionEntitlementService.php';
        $contents = file_get_contents($file);

        self::assertIsString($contents);
        self::assertStringContainsString('SubscriptionEntitlementComputationInterface', $contents);
        self::assertStringContainsString('->compute(', $contents);
        self::assertStringNotContainsString('new ComputedSubscriptionEntitlementData', $contents);
        self::assertStringNotContainsString('capabilityKeys', $contents);
    }

    public function test_contracts_commercial_catalogue_remain_framework_free_and_introduce_no_forbidden_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/CommercialCatalogue';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            foreach ($this->contractsForbiddenTokens() as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/',
                $contents,
                $file,
            );
        }
    }

    public function test_exact_admin_query_interface_inventory(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/CommercialCatalogue/AdminQueries';

        self::assertSame(
            [
                'BillingOptionCatalogueQueryInterface.php',
                'CapabilityDefinitionCatalogueQueryInterface.php',
                'PlanCatalogueQueryInterface.php',
                'PlanOfferingCatalogueQueryInterface.php',
            ],
            $this->phpBasenamesIn($root),
        );

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);
            self::assertMatchesRegularExpression('/deterministic/i', $contents, $file);
        }
    }

    public function test_exact_pagination_contract_inventory(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/CommercialCatalogue/Pagination';

        self::assertSame(
            [
                'OffsetPaginationInput.php',
                'OffsetPaginationMeta.php',
                'PaginatedBillingOptionData.php',
                'PaginatedCapabilityDefinitionData.php',
                'PaginatedPlanData.php',
                'PaginatedPlanOfferingData.php',
            ],
            $this->phpBasenamesIn($root),
        );

        self::assertSame(
            ['InvalidPaginatedResultException.php'],
            $this->phpBasenamesIn($root.'/Exceptions'),
        );
    }

    public function test_exact_mutation_command_inventory(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/CommercialCatalogue';

        $commands = [];
        foreach (glob($root.'/*Command.php') ?: [] as $file) {
            $commands[] = basename($file);
        }
        sort($commands);

        self::assertSame(
            [
                'ActivateCapabilityDefinitionCommand.php',
                'ActivatePlanCommand.php',
                'ActivatePlanOfferingCommand.php',
                'CreateBillingOptionCommand.php',
                'CreateCapabilityDefinitionCommand.php',
                'CreatePlanCommand.php',
                'CreatePlanOfferingCommand.php',
                'DeprecateCapabilityDefinitionCommand.php',
                'GrandfatherPlanCommand.php',
                'GrandfatherPlanOfferingCommand.php',
                'MakePlanOfferingUnavailableCommand.php',
                'MakePlanUnavailableCommand.php',
                'RetireCapabilityDefinitionCommand.php',
                'RetirePlanCommand.php',
                'RetirePlanOfferingCommand.php',
                'UpdateBillingOptionCommand.php',
                'UpdateCapabilityDefinitionCommand.php',
                'UpdatePlanDetailsCommand.php',
                'UpdatePlanOfferingCommand.php',
            ],
            $commands,
        );

        $updatePlanDetailsSource = file_get_contents($root.'/UpdatePlanDetailsCommand.php');
        self::assertIsString($updatePlanDetailsSource);
        self::assertStringContainsString('expectedVersion', $updatePlanDetailsSource);
    }

    public function test_architecture_tests_in_this_scope_perform_no_filesystem_mutation(): void
    {
        $lines = file(__FILE__, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);

        // The ban-list declaration line itself necessarily names its own tokens
        // and is excluded from the scan; every other line must not call them.
        $codeLines = array_filter(
            $lines,
            static fn (string $line): bool => ! str_contains($line, 'FILESYSTEM_MUTATOR_TOKENS'),
        );
        $contents = implode("\n", $codeLines);

        foreach (self::FILESYSTEM_MUTATOR_TOKENS as $mutator) {
            self::assertStringNotContainsString($mutator, $contents, $mutator);
        }
    }

    /** @return list<string> */
    private function applicationForbiddenTokens(): array
    {
        return [
            'Illuminate\\',
            'Laravel\\',
            'Infrastructure\\',
            'Presentation\\',
            'Controller',
            'Request',
            'Response',
            'Middleware',
            'Eloquent',
            'Model',
            'Queue',
            'Notification',
            'Route::',
            'Http\\',
            'Payment',
            'AddOn',
            'Professional Service',
        ];
    }

    /** @return list<string> */
    private function contractsForbiddenTokens(): array
    {
        return [
            'Illuminate\\',
            'Laravel\\',
            'Infrastructure\\',
            'Presentation\\',
            'Http\\Request',
            'Http\\Response',
            'Eloquent',
            'Repository',
            'LengthAwarePaginator',
            'Paginator',
            'Controller',
            'Middleware',
            'Policy',
            'Gate',
            'Route::',
            'stdClass',
        ];
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function phpBasenamesIn(string $directory): array
    {
        $files = [];
        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $files[] = basename($file);
        }
        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function phpServiceBasenamesIn(string $directory): array
    {
        $files = [];
        foreach (glob($directory.'/*Service.php') ?: [] as $file) {
            $files[] = basename($file);
        }
        sort($files);

        return $files;
    }
}
