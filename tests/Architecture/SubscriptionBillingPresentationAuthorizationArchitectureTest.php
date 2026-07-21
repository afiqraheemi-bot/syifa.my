<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Generalized from a Commercial-Catalogue-only check (relocated from
 * CommercialCatalogueHttpDeliveryArchitectureTest) so that every controller
 * under SubscriptionBilling's Presentation/Http/Controllers is covered, not
 * only ones whose filename happens to contain "CommercialCatalogue". A
 * filename-based exemption previously let a new controller
 * (PaymentProviderAdministrationController) contain exactly the ad-hoc
 * authorization logic this rule exists to forbid.
 */
final class SubscriptionBillingPresentationAuthorizationArchitectureTest extends TestCase
{
    public function test_no_presentation_controller_contains_ad_hoc_platform_authorization_logic(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation/Http/Controllers';
        $controllers = $this->phpFilesIn($root);

        self::assertNotEmpty($controllers, 'Expected at least one controller under '.$root);

        foreach ($controllers as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            foreach ([
                'PlatformAdministration\\Contracts\\Authorization\\PlatformAuthorizationInterface',
                'PlatformAdministration\\Contracts\\Authentication\\PlatformPrincipalResolverInterface',
                'PlatformAdministration\\Application\\Authorization\\AuthorizePlatformActionService',
                'PlatformPrincipal',
                'CommercialCataloguePlatformAuthorizationAdapter',
                'super_admin',
                'website_designer',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/->role\s*(?:===|==|!==|!=)/',
                $contents,
                $file,
            );
            self::assertDoesNotMatchRegularExpression(
                '/\bif\s*\(\s*!?\$\w+(?:->\w+)*->role\b/',
                $contents,
                $file,
            );
        }
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
}
