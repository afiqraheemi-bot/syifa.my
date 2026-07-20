<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationDecision;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\CommercialCataloguePlatformAuthorizationAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\DenyAllCommercialCatalogueAuthorization;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CommercialCatalogueHttpDeliveryArchitectureTest extends TestCase
{
    public function test_http_delivery_surface_contains_the_approved_controller_request_resource_and_collection_inventory(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation/Http';

        self::assertSame([
            $root.'/Controllers/CommercialCatalogueBillingOptionController.php',
            $root.'/Controllers/CommercialCatalogueCapabilityDefinitionController.php',
            $root.'/Controllers/CommercialCataloguePlanController.php',
            $root.'/Controllers/CommercialCataloguePlanOfferingController.php',
        ], $this->phpFilesIn($root.'/Controllers'));

        self::assertSame([
            $root.'/Requests/ActivateCapabilityDefinitionRequest.php',
            $root.'/Requests/ActivatePlanOfferingRequest.php',
            $root.'/Requests/ActivatePlanRequest.php',
            $root.'/Requests/DeprecateCapabilityDefinitionRequest.php',
            $root.'/Requests/GrandfatherPlanOfferingRequest.php',
            $root.'/Requests/GrandfatherPlanRequest.php',
            $root.'/Requests/IndexBillingOptionsRequest.php',
            $root.'/Requests/IndexCapabilityDefinitionsRequest.php',
            $root.'/Requests/IndexPlanOfferingsRequest.php',
            $root.'/Requests/IndexPlansRequest.php',
            $root.'/Requests/MakePlanOfferingUnavailableRequest.php',
            $root.'/Requests/MakePlanUnavailableRequest.php',
            $root.'/Requests/RetireCapabilityDefinitionRequest.php',
            $root.'/Requests/RetirePlanOfferingRequest.php',
            $root.'/Requests/RetirePlanRequest.php',
            $root.'/Requests/StoreBillingOptionRequest.php',
            $root.'/Requests/StoreCapabilityDefinitionRequest.php',
            $root.'/Requests/StorePlanOfferingRequest.php',
            $root.'/Requests/StorePlanRequest.php',
            $root.'/Requests/UpdateBillingOptionRequest.php',
            $root.'/Requests/UpdateCapabilityDefinitionRequest.php',
            $root.'/Requests/UpdatePlanOfferingRequest.php',
            $root.'/Requests/UpdatePlanRequest.php',
        ], $this->phpFilesIn($root.'/Requests'));

        self::assertSame([
            $root.'/Resources/BillingOptionResource.php',
            $root.'/Resources/CapabilityDefinitionResource.php',
            $root.'/Resources/PlanOfferingResource.php',
            $root.'/Resources/PlanResource.php',
        ], $this->phpFilesIn($root.'/Resources'));

        self::assertSame([
            $root.'/Collections/BillingOptionCollection.php',
            $root.'/Collections/CapabilityDefinitionCollection.php',
            $root.'/Collections/PlanCollection.php',
            $root.'/Collections/PlanOfferingCollection.php',
        ], $this->phpFilesIn($root.'/Collections'));
    }

    public function test_http_delivery_routes_expose_only_the_approved_commercial_catalogue_surface(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
        self::assertIsString($routes);

        foreach ([
            'ApiVersion::COMMERCIAL_CATALOGUE_PREFIX',
            "Route::prefix('plans')",
            "Route::prefix('billing-options')",
            "Route::prefix('capabilities')",
            "Route::prefix('plan-offerings')",
        ] as $path) {
            self::assertStringContainsString($path, $routes);
        }

        foreach ([
            '/billing-options/{billingOptionId}/activate',
            '/billing-options/{billingOptionId}/deprecate',
            '/billing-options/{billingOptionId}/retire',
            "Route::delete('/api/v1/platform/commercial-catalogue",
            'OpenApi',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $routes);
        }

        self::assertSame(1, substr_count($routes, 'Route::prefix(ApiVersion::COMMERCIAL_CATALOGUE_PREFIX)'));
    }

    public function test_http_delivery_surface_has_no_repository_or_infrastructure_imports_in_presentation_classes(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation/Http') as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            if (str_contains($file, '/Responses/CommercialCatalogueErrorResponseMapper.php')) {
                continue;
            }

            self::assertStringNotContainsString('App\\Modules\\SubscriptionBilling\\Infrastructure\\', $contents, $file);
            self::assertStringNotContainsString('Repository', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('TenantId', $contents, $file);
            self::assertStringNotContainsString('Entitlement', $contents, $file);
        }
    }

    public function test_http_delivery_controllers_do_not_contain_platform_authorization_logic(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation/Http/Controllers') as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            foreach ([
                'PlatformAdministration\\Contracts\\Authorization\\PlatformAuthorizationInterface',
                'PlatformAdministration\\Contracts\\Authentication\\PlatformPrincipalResolverInterface',
                'PlatformAdministration\\Application\\Authorization\\AuthorizePlatformActionService',
                'PlatformPrincipal',
                'CommercialCataloguePlatformAuthorizationAdapter',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }
        }
    }

    public function test_http_delivery_controllers_do_not_persist_audit_entries_directly(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation/Http/Controllers') as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            foreach ([
                'AuditEntryRecorderInterface',
                'AuditEntryRepositoryInterface',
                'AuditEntryData',
                'RecordAuditEntryService',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }
        }
    }

    public function test_production_authorization_binding_uses_the_platform_runtime_adapter(): void
    {
        $provider = file_get_contents(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Infrastructure/SubscriptionBillingServiceProvider.php');
        self::assertIsString($provider);
        self::assertStringContainsString(CommercialCataloguePlatformAuthorizationAdapter::class, $provider);
        self::assertStringNotContainsString(DenyAllCommercialCatalogueAuthorization::class, $provider);
    }

    public function test_authorization_contracts_reside_only_in_the_contracts_layer(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling';

        self::assertSame([
            $root.'/Contracts/Authorization/CommercialCatalogueAuthorizationDecision.php',
            $root.'/Contracts/Authorization/CommercialCatalogueAuthorizationInterface.php',
        ], $this->phpFilesIn($root.'/Contracts/Authorization'));

        self::assertSame([
            $root.'/Infrastructure/Authorization/CommercialCataloguePlatformAuthorizationAdapter.php',
            $root.'/Infrastructure/Authorization/DenyAllCommercialCatalogueAuthorization.php',
        ], $this->phpFilesIn($root.'/Infrastructure/Authorization'));

        self::assertFileExists($root.'/Contracts/Authorization/CommercialCatalogueAuthorizationInterface.php');
        self::assertFileExists($root.'/Contracts/Authorization/CommercialCatalogueAuthorizationDecision.php');
        self::assertFileExists($root.'/Infrastructure/Authorization/CommercialCataloguePlatformAuthorizationAdapter.php');
        self::assertFileExists($root.'/Infrastructure/Authorization/DenyAllCommercialCatalogueAuthorization.php');
        self::assertFileDoesNotExist($root.'/Presentation/Http/Authorization/CommercialCatalogueAuthorizationInterface.php');
        self::assertFileDoesNotExist($root.'/Presentation/Http/Authorization/CommercialCatalogueAuthorizationDecision.php');
        self::assertFileDoesNotExist($root.'/Presentation/Http/Authorization/CommercialCataloguePlatformAuthorizationAdapter.php');
        self::assertFileDoesNotExist($root.'/Presentation/Http/Authorization/DenyAllCommercialCatalogueAuthorization.php');
    }

    public function test_authorization_boundary_is_contracts_plus_infrastructure_without_presentation_imports(): void
    {
        $interface = file_get_contents(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/Authorization/CommercialCatalogueAuthorizationInterface.php');
        $decision = file_get_contents(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/Authorization/CommercialCatalogueAuthorizationDecision.php');
        $adapter = file_get_contents(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Infrastructure/Authorization/CommercialCataloguePlatformAuthorizationAdapter.php');
        $denyAll = file_get_contents(dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Infrastructure/Authorization/DenyAllCommercialCatalogueAuthorization.php');

        foreach ([$interface, $decision] as $source) {
            self::assertIsString($source);
            self::assertStringNotContainsString('Presentation\\Http\\Authorization', $source);
            self::assertStringNotContainsString('Infrastructure\\Authorization', $source);
            self::assertStringNotContainsString('Illuminate\\', $source);
            self::assertStringNotContainsString('Eloquent', $source);
            self::assertStringNotContainsString('PlatformAdministration', $source);
        }

        self::assertIsString($adapter);
        self::assertStringContainsString(CommercialCatalogueAuthorizationInterface::class, $adapter);
        self::assertStringContainsString('PlatformPrincipalResolverInterface', $adapter);
        self::assertStringContainsString('PlatformAuthorizationInterface', $adapter);
        foreach ([
            'Presentation\\Http\\Authorization',
            'Illuminate\\',
            'Eloquent',
            'Repository',
            'Controller',
            'Route',
            'Request',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $adapter);
        }

        self::assertIsString($denyAll);
        self::assertStringContainsString('Infrastructure\\Authorization', $denyAll);
        self::assertStringContainsString(CommercialCatalogueAuthorizationInterface::class, $denyAll);
        self::assertStringContainsString(CommercialCatalogueAuthorizationDecision::class, $denyAll);
        self::assertStringNotContainsString('Presentation\\Http\\Authorization', $denyAll);
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
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
}
