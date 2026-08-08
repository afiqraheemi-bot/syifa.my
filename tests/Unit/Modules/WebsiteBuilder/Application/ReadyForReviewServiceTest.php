<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReadyForReviewCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReadyForReviewService;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\WebsitePublicationReadinessEvaluator;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ContactSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReadyForReviewServiceTest extends TestCase
{
    private const string TENANT = '00000000-0000-4000-8000-000000000001';

    private const string WEBSITE = '00000000-0000-4000-8000-000000000002';

    private const string DESIGNER = '00000000-0000-4000-8000-000000000003';

    public function test_complete_enabled_content_transitions_and_persists_the_website(): void
    {
        $website = $this->website();
        $repository = new ReviewWebsiteRepository($website);
        $result = $this->service($repository, $this->draft(true))->handle($this->command());

        self::assertSame('ready_for_review', $result->toArray()['lifecycle']);
        self::assertSame(WebsiteLifecycle::ReadyForReview, $website->lifecycle());
        self::assertSame(2, $website->version());
        self::assertSame(1, $repository->saves);
        self::assertNull($website->publishedSnapshot());
    }

    public function test_current_ready_for_review_website_can_revalidate_without_a_second_transition(): void
    {
        $website = $this->website();
        $repository = new ReviewWebsiteRepository($website);
        $service = $this->service($repository, $this->draft(true));
        $first = $service->handle($this->command());

        $result = $service->handle($this->command(expectedVersion: 2));

        self::assertSame('ready_for_review', $result->toArray()['lifecycle']);
        self::assertSame(2, $website->version());
        self::assertSame(1, $repository->saves);
        self::assertSame(2, $first->toArray()['version']);
    }

    public function test_incomplete_enabled_content_is_rejected_without_persistence(): void
    {
        $website = $this->website();
        $repository = new ReviewWebsiteRepository($website);

        try {
            $this->service($repository, $this->draft(false))->handle($this->command());
            self::fail('Expected incomplete readiness evidence to be rejected.');
        } catch (InvalidWebsiteValueException $exception) {
            self::assertStringContainsString(
                'Homepage needs a headline before it can be reviewed.',
                $exception->getMessage(),
            );
            self::assertSame(WebsiteLifecycle::Draft, $website->lifecycle());
            self::assertSame(0, $repository->saves);
        }
    }

    public function test_stale_website_version_is_rejected_before_transition(): void
    {
        $this->expectException(StaleWebsiteWriteException::class);

        $this->service(
            new ReviewWebsiteRepository($this->website()),
            $this->draft(true),
        )->handle($this->command(expectedVersion: 0));
    }

    public function test_unassigned_designer_is_forbidden(): void
    {
        $this->expectException(WebsiteOperationForbiddenException::class);

        $this->service(
            new ReviewWebsiteRepository($this->website()),
            $this->draft(true),
        )->handle($this->command(assignedTenantId: $this->uuid(99)));
    }

    private function service(
        ReviewWebsiteRepository $websites,
        WebsiteDraftContent $draft,
    ): ReadyForReviewService {
        $codec = new WebsiteDraftSectionCodec;

        return new ReadyForReviewService(
            $websites,
            new ReviewDraftRepository($draft),
            new ReviewActiveServices,
            new WebsiteAuthorization,
            new WebsitePublicationReadinessEvaluator($codec),
        );
    }

    private function website(): Website
    {
        $website = Website::create(
            new WebsiteId(self::WEBSITE),
            new TenantId(self::TENANT),
            TemplateId::SyifaEssential,
            new WebsiteBranding(
                'Klinik Syifa',
                null,
                '#112233',
                '#445566',
                null,
                null,
                'hello@clinic.test',
                '+6012',
                'Kuala Lumpur',
            ),
            array_map(
                fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)),
                range(11, 19),
            ),
            new DateTimeImmutable('2026-08-27T00:00:00Z'),
        );
        foreach (array_slice($website->sections()->sections(), 1) as $section) {
            $website->disableSection($section->id, new DateTimeImmutable('2026-08-27T00:01:00Z'));
        }
        $website->synchronizeVersion(1);

        return $website;
    }

    private function draft(bool $completeHero): WebsiteDraftContent
    {
        $id = fn (int $suffix): SectionId => new SectionId($this->uuid($suffix));

        return new WebsiteDraftContent(
            new WebsiteId(self::WEBSITE),
            new TenantId(self::TENANT),
            1,
            [
                new HeroSectionContent($id(11), $completeHero ? 'Trusted healthcare' : null),
                new AboutSectionContent($id(12)),
                new ServicesSectionContent($id(13)),
                new DoctorsSectionContent($id(14)),
                new TestimonialsSectionContent($id(15)),
                new GallerySectionContent($id(16)),
                new FaqSectionContent($id(17)),
                new ContactSectionContent($id(18)),
                new BookingCtaSectionContent($id(19)),
            ],
        );
    }

    private function command(
        int $expectedVersion = 1,
        ?string $assignedTenantId = null,
    ): ReadyForReviewCommand {
        return new ReadyForReviewCommand(
            new WebsiteAuthorizationContext(
                self::DESIGNER,
                'website_designer',
                assignedTenantId: $assignedTenantId ?? self::TENANT,
            ),
            self::TENANT,
            self::WEBSITE,
            $expectedVersion,
            1,
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class ReviewWebsiteRepository implements WebsiteRepositoryInterface
{
    public int $saves = 0;

    public function __construct(private Website $website) {}

    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value
            && $websiteId->value === $this->website->id->value
                ? $this->website
                : null;
    }

    public function findByTenant(TenantId $tenantId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value ? $this->website : null;
    }

    public function save(Website $website): void
    {
        $this->saves++;
        $website->synchronizeVersion($website->version() + 1);
    }
}

final readonly class ReviewDraftRepository implements WebsiteDraftRepositoryInterface
{
    public function __construct(private WebsiteDraftContent $draft) {}

    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
    {
        return $tenantId->value === $this->draft->tenantId->value
            && $websiteId->value === $this->draft->websiteId->value
                ? $this->draft
                : null;
    }

    public function save(
        WebsiteDraftContent $draft,
        int $expectedVersion,
    ): WebsiteDraftContent {
        throw new \LogicException('Ready for review must not mutate the Website Draft.');
    }
}

final class ReviewActiveServices implements ActiveServiceReferenceReadInterface
{
    public function forTenant(string $tenantId): array
    {
        return [];
    }
}
