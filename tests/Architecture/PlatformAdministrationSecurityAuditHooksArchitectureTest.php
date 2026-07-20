<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PlatformAdministrationSecurityAuditHooksArchitectureTest extends TestCase
{
    public function test_platform_administration_authentication_and_authorization_services_depend_on_audit_contracts_only(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Application';

        $authentication = file_get_contents($root.'/Authentication/AuthenticatePlatformSessionService.php');
        $logout = file_get_contents($root.'/Authentication/LogoutPlatformSessionService.php');
        $authorization = file_get_contents($root.'/Authorization/AuthorizePlatformActionService.php');

        foreach ([$authentication, $logout, $authorization] as $source) {
            self::assertIsString($source);
            self::assertStringContainsString('AuditEntryRecorderInterface', $source);
            self::assertStringContainsString('AuditCorrelationIdResolverInterface', $source);
            self::assertStringContainsString('LoggerInterface', $source);
            self::assertStringNotContainsString('RecordAuditEntryService', $source);
            self::assertStringNotContainsString('RequestAuditCorrelationIdResolver', $source);
            self::assertStringNotContainsString('Illuminate\\Http\\Request', $source);
        }
    }

    public function test_platform_administration_presentation_controllers_do_not_persist_audit_entries_directly(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Presentation/Http/Controllers/PlatformSessionController.php',
        );

        self::assertIsString($controller);
        foreach ([
            'AuditEntryRecorderInterface',
            'AuditCorrelationIdResolverInterface',
            'RecordAuditEntryService',
            'LoggerInterface',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller);
        }
    }
}
