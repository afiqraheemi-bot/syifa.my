<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorData;
use App\Modules\PlatformAdministration\Domain\Authorization\AuthorizationDecision;
use App\Modules\PlatformAdministration\Domain\Authorization\CategoryGrant;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAdministrator;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformCategory;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformPermission;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use SplFileInfo;

final class PlatformAdministrationAuthorizationArchitectureTest extends TestCase
{
    public function test_authorization_foundation_introduces_domain_types_only_and_no_aggregate_root(): void
    {
        $domain = $this->moduleRoot().'/Domain/Authorization';

        self::assertFileExists($domain.'/PlatformAdministrator.php');
        self::assertFileExists($domain.'/PlatformCategory.php');
        self::assertFileExists($domain.'/PlatformPermission.php');
        self::assertFileExists($domain.'/CategoryGrant.php');
        self::assertFileExists($domain.'/AuthorizationDecision.php');
        self::assertFileExists($domain.'/PlatformAuthorizationService.php');
        self::assertFileExists($domain.'/ValueObjects/AuthorizationDecisionReason.php');
        self::assertFileDoesNotExist($domain.'/PermissionDecision.php');
        self::assertFileDoesNotExist($domain.'/ValueObjects/PermissionDecisionReason.php');

        self::assertDirectoryDoesNotExist($this->moduleRoot().'/Domain/Aggregates/Authorization');
        self::assertDirectoryDoesNotExist($this->moduleRoot().'/Domain/Aggregates/PlatformAdministrator');
        self::assertDirectoryDoesNotExist($this->moduleRoot().'/Domain/Aggregates/CategoryGrant');

        self::assertSame(PlatformAdministrator::class, (new ReflectionClass(PlatformAdministrator::class))->getName());
        self::assertSame(PlatformCategory::class, (new ReflectionClass(PlatformCategory::class))->getName());
        self::assertSame(PlatformPermission::class, (new ReflectionClass(PlatformPermission::class))->getName());
        self::assertSame(CategoryGrant::class, (new ReflectionClass(CategoryGrant::class))->getName());
        self::assertSame(AuthorizationDecision::class, (new ReflectionClass(AuthorizationDecision::class))->getName());
        self::assertSame(PlatformAuthorizationService::class, (new ReflectionClass(PlatformAuthorizationService::class))->getName());
    }

    public function test_platform_administrator_carries_exactly_one_platform_identity_reference(): void
    {
        $constructor = (new ReflectionClass(PlatformAdministrator::class))->getConstructor();
        self::assertNotNull($constructor);

        $parameterTypes = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $parameterTypes[$parameter->getName()] = $type instanceof ReflectionNamedType ? $type->getName() : null;
        }

        self::assertArrayHasKey('platformIdentityId', $parameterTypes);
        self::assertSame(PlatformIdentityId::class, $parameterTypes['platformIdentityId']);
        self::assertSame(
            ['id', 'platformIdentityId', 'status'],
            array_keys($parameterTypes),
        );
    }

    public function test_exactly_one_application_authorization_service_exists(): void
    {
        self::assertCount(1, $this->phpFilesIn($this->moduleRoot().'/Application/Authorization'));
    }

    public function test_super_admin_role_verification_boundary_exists(): void
    {
        $source = file_get_contents($this->moduleRoot().'/Application/Authorization/AuthorizePlatformActionService.php');
        self::assertIsString($source);
        self::assertStringContainsString('isSuperAdmin()', $source);
        self::assertStringContainsString('SuperAdminRoleRequired', $source);
        self::assertStringContainsString('isActive()', $source);
    }

    public function test_no_duplicate_platform_identity_source_exists(): void
    {
        foreach ([
            ...$this->phpFilesIn($this->moduleRoot().'/Domain/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Contracts/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Infrastructure/Persistence/Authorization'),
        ] as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            self::assertDoesNotMatchRegularExpression(
                '/\b(?:class|interface|enum)\s+\w*Identity\w*/',
                $source,
                $file,
            );
        }
    }

    public function test_domain_authorization_is_framework_independent_and_has_no_cross_module_dependency(): void
    {
        foreach ($this->phpFilesIn($this->moduleRoot().'/Domain/Authorization') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach ($this->forbiddenTokens() as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\)/',
                $source,
                $file,
            );
        }
    }

    public function test_contracts_authorization_is_framework_independent_and_has_no_cross_module_dependency(): void
    {
        foreach ($this->phpFilesIn($this->moduleRoot().'/Contracts/Authorization') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach ($this->forbiddenTokens() as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\)/',
                $source,
                $file,
            );
        }

        foreach ($this->phpFilesIn($this->moduleRoot().'/Contracts/Authorization') as $file) {
            if (! str_ends_with($file, 'Data.php')) {
                continue;
            }

            self::assertStringContainsString('final readonly class', file_get_contents($file) ?: '', $file);
        }
    }

    public function test_application_authorization_is_framework_free_and_depends_only_on_domain_and_contracts(): void
    {
        self::assertSame(
            [$this->moduleRoot().'/Application/Authorization/AuthorizePlatformActionService.php'],
            $this->phpFilesIn($this->moduleRoot().'/Application/Authorization'),
        );

        foreach ($this->phpFilesIn($this->moduleRoot().'/Application/Authorization') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach ($this->applicationForbiddenTokens() as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\(Domain|Contracts|Application)\\\\)/',
                $source,
                $file,
            );
        }
    }

    public function test_infrastructure_authorization_has_exact_inventory_and_introduces_no_domain_or_http_coupling(): void
    {
        $infrastructure = $this->moduleRoot().'/Infrastructure/Persistence/Authorization';

        self::assertSame(
            [
                $infrastructure.'/Exceptions/InvalidPlatformAuthorizationStorageStateException.php',
                $infrastructure.'/Mappers/PlatformAuthorizationPersistenceMapper.php',
                $infrastructure.'/PostgresCategoryGrantLookup.php',
                $infrastructure.'/PostgresPlatformAdministratorLookup.php',
                $infrastructure.'/PostgresPlatformCategoryLookup.php',
                $infrastructure.'/PostgresPlatformPermissionLookup.php',
            ],
            $this->phpFilesIn($infrastructure),
        );

        foreach ($this->phpFilesIn($infrastructure) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach (['Controller', 'Request', 'Resource', 'Middleware', 'Gate', 'Policy', 'Route::', 'Http\\'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\(Contracts|Infrastructure)\\\\)/',
                $source,
                $file,
            );
        }
    }

    public function test_authorization_foundation_introduces_no_forbidden_delivery_or_persistence_artifact(): void
    {
        foreach ($this->phpFilesIn($this->moduleRoot()) as $file) {
            if (! str_contains($file, '/Authorization/')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Request|Resource|Middleware|Gate|Policy|Provider)\.php$/i',
                $file,
            );
        }

        self::assertDirectoryDoesNotExist($this->moduleRoot().'/Presentation/Authorization');

        self::assertSame(
            [$this->root().'/database/migrations/platform_administration/2026_07_16_000001_create_platform_authorization_tables.php'],
            glob($this->root().'/database/migrations/*/*authoriz*') ?: [],
        );
        self::assertSame(
            [],
            glob($this->root().'/database/migrations/*/*category_grant*') ?: [],
        );
    }

    public function test_authorization_migration_never_deletes_and_forbids_json_permission_membership(): void
    {
        $migration = $this->root().'/database/migrations/platform_administration/2026_07_16_000001_create_platform_authorization_tables.php';
        $source = file_get_contents($migration);
        self::assertIsString($source);

        foreach (['->delete(', '->truncate(', 'dropColumn', 'onDelete(\'cascade\')', 'cascadeOnDelete'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source, $forbidden);
        }

        self::assertStringNotContainsString('json', mb_strtolower($source));
        self::assertStringContainsString('platform_category_grant_permissions', $source);
        self::assertStringNotContainsString('tenant_id', $source);
    }

    public function test_authorization_persistence_infrastructure_never_issues_a_delete_or_wildcard_permission(): void
    {
        foreach ($this->phpFilesIn($this->moduleRoot().'/Infrastructure/Persistence/Authorization') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach (['->delete(', '->truncate(', "'*'", '"*"'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }

        foreach ($this->phpFilesIn($this->moduleRoot().'/Application/Authorization') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach (['->delete(', "'*'", '"*"'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_authorization_does_not_reference_authentication_rbac_or_entitlement_concepts(): void
    {
        foreach ([
            ...$this->phpFilesIn($this->moduleRoot().'/Domain/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Application/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Infrastructure/Persistence/Authorization'),
        ] as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach (['Session', 'JWT', 'Password', 'Credential', 'TenantId', 'Entitlement', 'Subscription', 'SubscriptionBilling'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_authorization_profile_carries_no_authentication_credential_or_tenant_property(): void
    {
        foreach ([PlatformAdministrator::class, PlatformAdministratorData::class] as $class) {
            $constructor = (new ReflectionClass($class))->getConstructor();
            self::assertNotNull($constructor);

            $propertyNames = array_map(
                static fn (ReflectionParameter $parameter): string => $parameter->getName(),
                $constructor->getParameters(),
            );

            self::assertSame(
                [],
                array_intersect($propertyNames, [
                    'password', 'passwordHash', 'credential', 'session', 'sessionToken',
                    'token', 'mfaSecret', 'tenantId', 'role', 'permissions',
                ]),
                $class,
            );
        }
    }

    public function test_no_rbac_engine_or_authentication_implementation_is_introduced(): void
    {
        foreach ([
            ...$this->phpFilesIn($this->moduleRoot().'/Domain/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Contracts/Authorization'),
        ] as $file) {
            self::assertDoesNotMatchRegularExpression('/(?:RBAC|Authentication)/i', basename($file), $file);

            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            self::assertDoesNotMatchRegularExpression(
                '/\b(?:class|interface|enum)\s+\w*(?:RBAC|Authentication)\w*/i',
                $source,
                $file,
            );
        }
    }

    public function test_no_generic_authorization_manager_acl_or_policy_engine_is_introduced(): void
    {
        foreach ([
            ...$this->phpFilesIn($this->moduleRoot().'/Application/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Infrastructure/Persistence/Authorization'),
        ] as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:class|interface)\s+\w*(?:AuthorizationManager|AclEngine|RbacEngine|PolicyEngine|PlatformFacade)\w*/i',
                file_get_contents($file) ?: '',
                $file,
            );
        }
    }

    public function test_authorization_decision_replaces_permission_decision_everywhere(): void
    {
        self::assertFileDoesNotExist($this->moduleRoot().'/Domain/Authorization/PermissionDecision.php');
        self::assertFileDoesNotExist($this->moduleRoot().'/Contracts/Authorization/PermissionDecisionData.php');
        self::assertFileDoesNotExist($this->moduleRoot().'/Domain/Authorization/ValueObjects/PermissionDecisionReason.php');

        foreach ([
            ...$this->phpFilesIn($this->moduleRoot().'/Domain/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Contracts/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Application/Authorization'),
        ] as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            self::assertDoesNotMatchRegularExpression('/\bPermissionDecision\b/', $source, $file);
            self::assertStringNotContainsString('PermissionDecisionData', $source, $file);
            self::assertStringNotContainsString('PermissionDecisionReason', $source, $file);
        }

        $decisionSource = file_get_contents($this->moduleRoot().'/Domain/Authorization/AuthorizationDecision.php');
        self::assertIsString($decisionSource);
        self::assertStringNotContainsString('public function __construct(', $decisionSource);

        $reasonSource = file_get_contents($this->moduleRoot().'/Domain/Authorization/ValueObjects/AuthorizationDecisionReason.php');
        self::assertIsString($reasonSource);
        self::assertStringContainsString('enum AuthorizationDecisionReason: string', $reasonSource);

        foreach ([
            'Allowed',
            'AdministratorNotActive',
            'GrantNotActive',
            'CategoryNotActive',
            'PermissionNotActive',
            'PermissionNotGranted',
            'CategoryNotFound',
            'PermissionNotFound',
            'GrantNotFound',
            'PermissionCategoryMismatch',
            'PlatformIdentityNotFound',
            'PlatformIdentityNotActive',
            'SuperAdminRoleRequired',
            'AdministratorProfileNotFound',
            'AdministratorProfileAmbiguous',
        ] as $case) {
            self::assertStringContainsString('case '.$case.' = ', $reasonSource, $case);
        }
    }

    public function test_platform_authorization_service_is_the_sole_evaluator_and_category_grant_no_longer_evaluates(): void
    {
        $serviceSource = file_get_contents($this->moduleRoot().'/Domain/Authorization/PlatformAuthorizationService.php');
        self::assertIsString($serviceSource);
        self::assertStringContainsString('function evaluate(', $serviceSource);
        self::assertStringContainsString('AuthorizationDecision::allowed()', $serviceSource);
        self::assertStringContainsString('AuthorizationDecision::denied(', $serviceSource);

        $grantSource = file_get_contents($this->moduleRoot().'/Domain/Authorization/CategoryGrant.php');
        self::assertIsString($grantSource);
        self::assertStringNotContainsString('function evaluate(', $grantSource);
        self::assertStringNotContainsString('AuthorizationDecision', $grantSource);
        self::assertStringNotContainsString('assertReferences', $grantSource);

        foreach ([
            ...$this->phpFilesIn($this->moduleRoot().'/Domain/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Application/Authorization'),
            ...$this->phpFilesIn($this->moduleRoot().'/Infrastructure/Persistence/Authorization'),
        ] as $file) {
            if (str_ends_with($file, 'PlatformAuthorizationService.php')) {
                continue;
            }

            $source = file_get_contents($file);
            self::assertIsString($source, $file);
            self::assertStringNotContainsString('function evaluate(', $source, $file);
        }

        $applicationSource = file_get_contents(
            $this->moduleRoot().'/Application/Authorization/AuthorizePlatformActionService.php',
        );
        self::assertIsString($applicationSource);
        self::assertStringContainsString('$this->evaluator->evaluate(', $applicationSource);
    }

    /** @return list<string> */
    private function forbiddenTokens(): array
    {
        return [
            'Illuminate\\',
            'Laravel\\',
            'Infrastructure\\',
            'Presentation\\',
            'Controllers\\',
            'Http\\',
            'Eloquent',
            'Model',
            'Repository',
            'Request',
            'Resource',
            'Middleware',
            'Gate',
            'Policy',
            'ServiceProvider',
            'Queue',
            'Notification',
            'Route::',
        ];
    }

    /** @return list<string> */
    private function applicationForbiddenTokens(): array
    {
        return [
            'Illuminate\\',
            'Laravel\\',
            'Infrastructure\\',
            'Presentation\\',
            'Controllers\\',
            'Http\\',
            'Eloquent',
            'Model',
            'Repository',
            'Request',
            'Resource',
            'Middleware',
            'Gate',
            'Policy',
            'ServiceProvider',
            'Queue',
            'Notification',
            'Route::',
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

    private function moduleRoot(): string
    {
        return $this->root().'/app/Modules/PlatformAdministration';
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
