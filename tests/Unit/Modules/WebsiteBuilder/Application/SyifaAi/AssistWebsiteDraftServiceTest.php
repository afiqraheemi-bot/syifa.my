<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application\SyifaAi;

use App\Modules\Booking\Contracts\Queries\ActiveServiceCatalogueReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Modules\WebsiteBuilder\Application\SyifaAi\AssistWebsiteDraftCommand;
use App\Modules\WebsiteBuilder\Application\SyifaAi\AssistWebsiteDraftService;
use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiNotReadyException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiProviderException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiCapability;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationRequest;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationResult;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiSection;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiUsageRecord;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageRepositoryInterface;
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
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssistWebsiteDraftServiceTest extends TestCase
{
    private const string TENANT = '00000000-0000-4000-8000-000000000002';

    private const string WEBSITE = '00000000-0000-4000-8000-000000000001';

    #[Test]
    public function it_requires_a_section_before_requesting_content_suggestions(): void
    {
        $provider = new RecordingSyifaAiProvider;
        $usage = new InMemorySyifaAiUsageRepository;

        $this->expectException(SyifaAiNotReadyException::class);

        $this->service($provider, $usage)->assist($this->command(section: null));

        self::assertNull($provider->lastRequest);
        self::assertSame([], $usage->records);
    }

    #[Test]
    public function it_blocks_requests_once_the_monthly_token_allowance_is_reached(): void
    {
        $provider = new RecordingSyifaAiProvider;
        $usage = new InMemorySyifaAiUsageRepository(usedThisMonth: 250_000);
        config()->set('syifa_ai.monthly_tenant_token_limit', 250_000);

        $this->expectException(SyifaAiNotReadyException::class);

        $this->service($provider, $usage)->assist($this->command());

        self::assertNull($provider->lastRequest);
    }

    #[Test]
    public function it_sends_only_the_requested_section_and_records_completed_usage_on_success(): void
    {
        $provider = new RecordingSyifaAiProvider(new SyifaAiGenerationResult(
            'Hero lebih jelas',
            'Ringkasan cadangan.',
            [],
            [],
            [],
            'gpt-5.6-luna-2026-08-01',
            321,
            123,
        ));
        $usage = new InMemorySyifaAiUsageRepository;

        $result = $this->service($provider, $usage)->assist($this->command());

        self::assertSame('Hero lebih jelas', $result->title);
        self::assertNotNull($provider->lastRequest);
        self::assertSame(SyifaAiCapability::ContentAssistant, $provider->lastRequest->capability);
        self::assertSame('Klinik Afiq', $provider->lastRequest->context['clinic']['name']);
        self::assertSame(1, count($provider->lastRequest->context['draft_sections']));
        self::assertSame('HERO', $provider->lastRequest->context['draft_sections'][0]['type']);

        self::assertCount(1, $usage->records);
        self::assertSame('completed', $usage->records[0]->status);
        self::assertSame(321, $usage->records[0]->inputTokens);
        self::assertSame(123, $usage->records[0]->outputTokens);
    }

    #[Test]
    public function it_records_failed_usage_and_rethrows_when_the_provider_fails(): void
    {
        $provider = new RecordingSyifaAiProvider(throws: new SyifaAiProviderException('Upstream error.'));
        $usage = new InMemorySyifaAiUsageRepository;

        try {
            $this->service($provider, $usage)->assist($this->command());
            self::fail('Expected a SyifaAiProviderException to propagate.');
        } catch (SyifaAiProviderException $exception) {
            self::assertSame('Upstream error.', $exception->getMessage());
        }

        self::assertCount(1, $usage->records);
        self::assertSame('failed', $usage->records[0]->status);
        self::assertSame(0, $usage->records[0]->inputTokens);
        self::assertSame(0, $usage->records[0]->outputTokens);
    }

    private function service(
        SyifaAiProviderInterface $provider,
        SyifaAiUsageRepositoryInterface $usage,
    ): AssistWebsiteDraftService {
        $codec = new WebsiteDraftSectionCodec;
        $draftRepository = new InMemoryWebsiteDraftRepository($this->draft());
        $websiteRepository = new InMemoryWebsiteRepository($this->website());

        return new AssistWebsiteDraftService(
            new ManageWebsiteDraftContentService(
                $draftRepository,
                new WebsiteAuthorization,
                $codec,
                new class implements ActiveServiceReferenceReadInterface
                {
                    public function forTenant(string $tenantId): array
                    {
                        return [];
                    }
                },
            ),
            new ManageWebsiteContentService($websiteRepository, new WebsiteAuthorization),
            new class implements ActiveServiceCatalogueReaderInterface
            {
                public function forTenant(string $tenantId): array
                {
                    return [new PublicBookingFormServiceData('svc-1', 'General Consultation')];
                }
            },
            $provider,
            $usage,
        );
    }

    private function command(?SyifaAiSection $section = SyifaAiSection::Hero): AssistWebsiteDraftCommand
    {
        return new AssistWebsiteDraftCommand(
            new WebsiteAuthorizationContext($this->uuid(10), 'clinic_owner', actorTenantId: self::TENANT),
            self::TENANT,
            self::WEBSITE,
            SyifaAiCapability::ContentAssistant,
            $section,
            null,
        );
    }

    private function website(): Website
    {
        $website = Website::create(
            new WebsiteId(self::WEBSITE),
            new TenantId(self::TENANT),
            TemplateId::SyifaEssential,
            new WebsiteBranding('Klinik Afiq', null, '#112233', '#445566', null, null, 'hello@example.test', '+60123456789', 'Kuala Lumpur'),
            array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)),
            new DateTimeImmutable('2099-01-01T00:00:00Z'),
        );
        $website->synchronizeVersion(1);

        return $website;
    }

    private function draft(): WebsiteDraftContent
    {
        return new WebsiteDraftContent(
            new WebsiteId(self::WEBSITE),
            new TenantId(self::TENANT),
            1,
            [
                new HeroSectionContent(new SectionId($this->uuid(11)), 'Selamat datang'),
                new AboutSectionContent(new SectionId($this->uuid(12)), 'Tentang kami', 'Klinik keluarga.'),
                new ServicesSectionContent(new SectionId($this->uuid(13))),
                new DoctorsSectionContent(new SectionId($this->uuid(14))),
                new TestimonialsSectionContent(new SectionId($this->uuid(15))),
                new GallerySectionContent(new SectionId($this->uuid(16))),
                new FaqSectionContent(new SectionId($this->uuid(17))),
                new ContactSectionContent(new SectionId($this->uuid(18))),
                new BookingCtaSectionContent(new SectionId($this->uuid(19))),
            ],
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryWebsiteDraftRepository implements WebsiteDraftRepositoryInterface
{
    public function __construct(private WebsiteDraftContent $draft) {}

    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
    {
        return $tenantId->value === $this->draft->tenantId->value
            && $websiteId->value === $this->draft->websiteId->value
                ? $this->draft
                : null;
    }

    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent
    {
        return $this->draft = $draft;
    }
}

final class InMemoryWebsiteRepository implements WebsiteRepositoryInterface
{
    public function __construct(private Website $website) {}

    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
    {
        return $this->website->id->value === $websiteId->value ? $this->website : null;
    }

    public function findByTenant(TenantId $tenantId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value ? $this->website : null;
    }

    public function save(Website $website): void
    {
        $this->website = $website;
    }
}

final class RecordingSyifaAiProvider implements SyifaAiProviderInterface
{
    public ?SyifaAiGenerationRequest $lastRequest = null;

    public function __construct(
        private ?SyifaAiGenerationResult $result = null,
        private ?\Throwable $throws = null,
    ) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function generate(SyifaAiGenerationRequest $request): SyifaAiGenerationResult
    {
        $this->lastRequest = $request;
        if ($this->throws !== null) {
            throw $this->throws;
        }

        return $this->result ?? new SyifaAiGenerationResult('Title', 'Summary', [], [], [], 'model', 0, 0);
    }
}

final class InMemorySyifaAiUsageRepository implements SyifaAiUsageRepositoryInterface
{
    /** @var list<SyifaAiUsageRecord> */
    public array $records = [];

    public function __construct(private int $usedThisMonth = 0) {}

    public function tokensUsedThisMonth(string $tenantId): int
    {
        return $this->usedThisMonth;
    }

    public function record(SyifaAiUsageRecord $record): void
    {
        $this->records[] = $record;
    }
}
