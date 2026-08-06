<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\WebsiteTemplateChangePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebsiteTemplateChangePolicyTest extends TestCase
{
    public function test_clinic_owner_cannot_change_template_after_initial_publication(): void
    {
        $this->expectException(WebsiteOperationForbiddenException::class);

        WebsiteTemplateChangePolicy::assertPermitted('clinic_owner', 1, true);
    }

    #[DataProvider('permittedChanges')]
    public function test_approved_template_changes_remain_permitted(
        string $role,
        int $publishedVersion,
        bool $changesTemplate,
    ): void {
        WebsiteTemplateChangePolicy::assertPermitted($role, $publishedVersion, $changesTemplate);
        self::assertTrue(true);
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function permittedChanges(): iterable
    {
        yield 'owner before publish' => ['clinic_owner', 0, true];
        yield 'owner retains current template after publish' => ['clinic_owner', 1, false];
        yield 'designer may change a published draft template' => ['website_designer', 1, true];
    }
}
