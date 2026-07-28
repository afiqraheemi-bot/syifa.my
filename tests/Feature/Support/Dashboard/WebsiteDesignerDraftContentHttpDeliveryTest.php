<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Dashboard;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
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
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerDraftContentController;
use DateTimeImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WebsiteDesignerDraftContentHttpDeliveryTest extends TestCase
{
    public const string DESIGNER = '00000000-0000-4000-8000-000000000001';

    public const string TENANT = '00000000-0000-4000-8000-000000000002';

    public const string WEBSITE = '00000000-0000-4000-8000-000000000003';

    public const string JOB = '00000000-0000-4000-8000-000000000004';

    #[Test]
    public function update_uses_assignment_owned_scope_and_returns_the_next_version(): void
    {
        $codec = new WebsiteDraftSectionCodec;
        $repository = new HttpDraftRepository($this->draft());
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new HttpActiveServiceReferences,
        );
        $sections = array_map($codec->encode(...), $repository->draft->sections);
        $sections[1]['heading'] = 'About Klinik Syifa';
        $sections[1]['description'] = 'Compassionate care for every family.';
        $sections[2]['items'] = [[
            'service_id' => '00000000-0000-4000-8000-000000000020',
            'display_order' => 1,
            'is_featured' => true,
        ]];
        $sections[3]['profiles'] = [[
            'id' => '00000000-0000-4000-8000-000000000030',
            'name' => 'Dr Aisyah',
            'professional_title' => 'Family Medicine',
            'visible' => true,
            'photo_asset_id' => null,
        ]];
        $sections[4]['testimonials'] = [[
            'id' => '00000000-0000-4000-8000-000000000035',
            'quote' => 'Professional and compassionate care.',
            'author_name' => 'Patient One',
            'featured' => true,
        ]];
        $sections[5]['images'] = [[
            'id' => '00000000-0000-4000-8000-000000000040',
            'asset_id' => '00000000-0000-4000-8000-000000000041',
            'alt_text' => 'Clinic reception',
            'caption' => 'A welcoming reception area.',
            'decorative' => false,
        ]];
        $sections[6]['entries'] = [[
            'id' => '00000000-0000-4000-8000-000000000045',
            'question' => 'When are you open?',
            'answer' => 'We are open every weekday.',
        ]];
        $sections[8]['heading'] = 'Ready to book?';
        $sections[8]['description'] = 'Choose a suitable appointment time.';
        $sections[8]['button_label'] = 'Book now';
        $request = Request::create(
            '/api/v1/platform/onboarding/'.self::JOB.'/website-draft',
            'PATCH',
            [
                'version' => 1,
                'tenant_id' => '00000000-0000-4000-8000-000000000099',
                'website_id' => '00000000-0000-4000-8000-000000000099',
                'sections' => $sections,
            ],
        );
        $request->attributes->set(AuthorizationContext::class, new AuthorizationContext(
            'platform_identity',
            self::DESIGNER,
            null,
            'website_designer',
            'Designer',
            'workforce',
            [],
        ));

        $response = (new WebsiteDesignerDraftContentController)->update(
            $request,
            self::JOB,
            new AssignedJobRead,
            $service,
        );

        self::assertSame(200, $response->status());
        self::assertSame(2, $response->getData(true)['data']['version']);
        self::assertSame(self::TENANT, $repository->draft->tenantId->value);
        self::assertSame(self::WEBSITE, $repository->draft->websiteId->value);
        self::assertSame('About Klinik Syifa', $response->getData(true)['data']['sections'][1]['heading']);
        self::assertSame(
            '00000000-0000-4000-8000-000000000020',
            $response->getData(true)['data']['sections'][2]['items'][0]['service_id'],
        );
        self::assertSame(
            'Dr Aisyah',
            $response->getData(true)['data']['sections'][3]['profiles'][0]['name'],
        );
        self::assertSame(
            'Patient One',
            $response->getData(true)['data']['sections'][4]['testimonials'][0]['author_name'],
        );
        self::assertSame(
            'Clinic reception',
            $response->getData(true)['data']['sections'][5]['images'][0]['alt_text'],
        );
        self::assertSame(
            'When are you open?',
            $response->getData(true)['data']['sections'][6]['entries'][0]['question'],
        );
        self::assertSame(
            'Book now',
            $response->getData(true)['data']['sections'][8]['button_label'],
        );

        $conflict = (new WebsiteDesignerDraftContentController)->update(
            $request,
            self::JOB,
            new AssignedJobRead,
            $service,
        );
        self::assertSame(409, $conflict->status());
        self::assertSame('website_draft.stale', $conflict->getData(true)['type']);
    }

    private function draft(): WebsiteDraftContent
    {
        $id = static fn (int $number): SectionId => new SectionId(sprintf(
            '00000000-0000-4000-8000-%012d',
            $number,
        ));

        return new WebsiteDraftContent(
            new WebsiteId(self::WEBSITE),
            new TenantId(self::TENANT),
            1,
            [
                new HeroSectionContent($id(11)),
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
}

final class AssignedJobRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        throw new \RuntimeException('Not used by this focused test.');
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        if ($platformIdentityId !== WebsiteDesignerDraftContentHttpDeliveryTest::DESIGNER
            || $onboardingJobId !== WebsiteDesignerDraftContentHttpDeliveryTest::JOB) {
            return null;
        }

        $at = new DateTimeImmutable('2026-08-26T00:00:00Z');

        return new WebsiteDesignerJobDetailData(
            '00000000-0000-4000-8000-000000000005',
            WebsiteDesignerDraftContentHttpDeliveryTest::JOB,
            WebsiteDesignerDraftContentHttpDeliveryTest::TENANT,
            WebsiteDesignerDraftContentHttpDeliveryTest::WEBSITE,
            'ASSIGNED',
            $at,
            $at,
            [],
        );
    }
}

final class HttpDraftRepository implements WebsiteDraftRepositoryInterface
{
    public function __construct(public WebsiteDraftContent $draft) {}

    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
    {
        return $tenantId->value === $this->draft->tenantId->value
            && $websiteId->value === $this->draft->websiteId->value
                ? $this->draft
                : null;
    }

    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent
    {
        if ($expectedVersion !== $this->draft->version) {
            throw new StaleWebsiteWriteException(
                'Website Draft write rejected because its version is stale.',
            );
        }

        return $this->draft = new WebsiteDraftContent(
            $draft->websiteId,
            $draft->tenantId,
            $expectedVersion + 1,
            $draft->sections,
        );
    }
}

final readonly class HttpActiveServiceReferences implements ActiveServiceReferenceReadInterface
{
    public function forTenant(string $tenantId): array
    {
        return ['00000000-0000-4000-8000-000000000020'];
    }
}
