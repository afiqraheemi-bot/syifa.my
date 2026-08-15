<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Dashboard;

use App\Modules\Booking\Contracts\Queries\ActiveServiceCatalogueReaderInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\WebsiteBuilder\Application\SyifaAi\AssistWebsiteDraftService;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationRequest;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationResult;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiUsageRecord;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerSyifaAiController;
use DateTimeImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class WebsiteDesignerSyifaAiAuthorizationTest extends TestCase
{
    public const string DESIGNER = '00000000-0000-4000-8000-000000000001';

    public const string OTHER_DESIGNER = '00000000-0000-4000-8000-000000000099';

    public const string JOB = '00000000-0000-4000-8000-000000000004';

    #[Test]
    public function guest_cannot_reach_designer_ai_assistance(): void
    {
        $response = $this->postJson(
            route('website-designer.syifa-ai.assist', '00000000-0000-4000-8000-000000000001'),
            ['capability' => 'content_assistant', 'section' => 'HERO'],
        );

        self::assertContains($response->status(), [401, 403]);
    }

    #[Test]
    public function guest_cannot_reach_clinic_owner_ai_assistance(): void
    {
        $response = $this->postJson(
            route('clinic-owner.syifa-ai.assist'),
            ['capability' => 'content_assistant', 'section' => 'HERO'],
        );

        self::assertContains($response->status(), [401, 403]);
    }

    #[Test]
    public function a_designer_cannot_request_ai_assistance_for_a_job_not_assigned_to_them(): void
    {
        $request = Request::create(
            '/api/v1/platform/onboarding/'.self::JOB.'/syifa-ai',
            'POST',
            ['capability' => 'designer_copilot'],
        );
        $request->attributes->set(AuthorizationContext::class, new AuthorizationContext(
            'platform_identity',
            self::OTHER_DESIGNER,
            null,
            'website_designer',
            'Designer',
            'workforce',
            [],
        ));

        $this->expectException(NotFoundHttpException::class);

        (new WebsiteDesignerSyifaAiController)->__invoke(
            $request,
            self::JOB,
            new SingleJobAssignmentRead,
            $this->assistantThatMustNotBeCalled(),
        );
    }

    private function assistantThatMustNotBeCalled(): AssistWebsiteDraftService
    {
        $throwing = static function (): never {
            throw new RuntimeException('SYIFA AI must not be invoked for an unassigned job.');
        };

        return new AssistWebsiteDraftService(
            new ManageWebsiteDraftContentService(
                new class($throwing) implements WebsiteDraftRepositoryInterface
                {
                    public function __construct(private \Closure $throwing) {}

                    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
                    {
                        ($this->throwing)();
                    }

                    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent
                    {
                        ($this->throwing)();
                    }
                },
                new WebsiteAuthorization,
                new WebsiteDraftSectionCodec,
                new class implements ActiveServiceReferenceReadInterface
                {
                    public function forTenant(string $tenantId): array
                    {
                        return [];
                    }
                },
            ),
            new ManageWebsiteContentService(
                new class($throwing) implements WebsiteRepositoryInterface
                {
                    public function __construct(private \Closure $throwing) {}

                    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
                    {
                        ($this->throwing)();
                    }

                    public function findByTenant(TenantId $tenantId): ?Website
                    {
                        ($this->throwing)();
                    }

                    public function save(Website $website): void
                    {
                        ($this->throwing)();
                    }
                },
                new WebsiteAuthorization,
            ),
            new class($throwing) implements ActiveServiceCatalogueReaderInterface
            {
                public function __construct(private \Closure $throwing) {}

                public function forTenant(string $tenantId): array
                {
                    ($this->throwing)();
                }
            },
            new class($throwing) implements SyifaAiProviderInterface
            {
                public function __construct(private \Closure $throwing) {}

                public function isConfigured(): bool
                {
                    return true;
                }

                public function generate(SyifaAiGenerationRequest $request): SyifaAiGenerationResult
                {
                    ($this->throwing)();
                }
            },
            new class($throwing) implements SyifaAiUsageRepositoryInterface
            {
                public function __construct(private \Closure $throwing) {}

                public function tokensUsedThisMonth(string $tenantId): int
                {
                    ($this->throwing)();
                }

                public function record(SyifaAiUsageRecord $record): void
                {
                    ($this->throwing)();
                }
            },
        );
    }
}

final class SingleJobAssignmentRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        throw new RuntimeException('Not used by this focused test.');
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        if ($platformIdentityId !== WebsiteDesignerSyifaAiAuthorizationTest::DESIGNER
            || $onboardingJobId !== WebsiteDesignerSyifaAiAuthorizationTest::JOB) {
            return null;
        }

        $at = new DateTimeImmutable('2026-08-26T00:00:00Z');

        return new WebsiteDesignerJobDetailData(
            '00000000-0000-4000-8000-000000000005',
            WebsiteDesignerSyifaAiAuthorizationTest::JOB,
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            'ASSIGNED',
            1,
            $at,
            $at,
            [],
        );
    }
}
