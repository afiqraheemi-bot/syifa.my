<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\LoadDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\SaveDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
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
use PHPUnit\Framework\TestCase;

final class ManageWebsiteDraftContentServiceTest extends TestCase
{
    private const string TENANT = '00000000-0000-4000-8000-000000000001';

    private const string WEBSITE = '00000000-0000-4000-8000-000000000002';

    private const string DESIGNER = '00000000-0000-4000-8000-000000000003';

    public function test_assigned_designer_loads_and_saves_a_complete_draft_with_optimistic_version(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );

        $loaded = $service->load(new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE));
        $sections = $loaded->toArray()['sections'];
        $sections[0]['headline'] = 'Trusted care, close to home';

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ));

        self::assertSame(2, $saved->toArray()['version']);
        self::assertSame('Trusted care, close to home', $saved->toArray()['sections'][0]['headline']);
        self::assertSame('Trusted care, close to home', $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'][0]['headline']);
    }

    public function test_designer_without_the_tenant_assignment_is_denied_before_repository_access(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            new WebsiteDraftSectionCodec,
            new FixedActiveServiceReferences,
        );

        $this->expectException(WebsiteOperationForbiddenException::class);

        $service->load(new LoadDraftWebsiteContent(
            new WebsiteAuthorizationContext(
                self::DESIGNER,
                'website_designer',
                assignedTenantId: '00000000-0000-4000-8000-000000000099',
            ),
            self::TENANT,
            self::WEBSITE,
        ));
    }

    public function test_existing_hero_validation_rejects_an_unpaired_cta(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $sections[0]['primary_cta_label'] = 'Book now';
        $sections[0]['primary_cta_target'] = null;

        $this->expectException(InvalidWebsiteValueException::class);
        $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ));
    }

    /**
     * Regression: the Hero CTA editor is a single plain text field with no
     * way to add a scheme, so a bare domain like "example.com" used to be
     * rejected outright by SectionContentRules::optionalTarget(), which only
     * accepts a relative path or an absolute "https://..." destination. A
     * missing scheme is now added automatically.
     */
    public function test_hero_cta_target_without_a_scheme_is_saved_as_an_absolute_https_url(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $sections[0]['primary_cta_label'] = 'Book now';
        $sections[0]['primary_cta_target'] = 'booking.klinikanda.example';

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ));

        self::assertSame('https://booking.klinikanda.example', $saved->toArray()['sections'][0]['primary_cta_target']);
    }

    /**
     * A relative in-site path must never be treated as a bare domain missing
     * its scheme.
     */
    public function test_hero_cta_target_relative_path_is_left_unchanged(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $sections[0]['primary_cta_label'] = 'Book now';
        $sections[0]['primary_cta_target'] = '/booking';

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ));

        self::assertSame('/booking', $saved->toArray()['sections'][0]['primary_cta_target']);
    }

    public function test_about_update_preserves_other_sections_and_invalid_content_does_not_advance_version(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $heroId = $sections[0]['section_id'];
        $sections[1]['heading'] = 'About Klinik Syifa';
        $sections[1]['description'] = 'Compassionate care for every family.';

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame('About Klinik Syifa', $saved['sections'][1]['heading']);
        self::assertSame($heroId, $saved['sections'][0]['section_id']);
        self::assertCount(9, $saved['sections']);

        $saved['sections'][1]['description'] = str_repeat('x', 5001);
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected invalid About content to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    public function test_services_accept_only_active_tenant_references_and_preserve_order(): void
    {
        $activeId = '00000000-0000-4000-8000-000000000040';
        $inactiveId = '00000000-0000-4000-8000-000000000041';
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences([$activeId]),
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $sections[2]['items'] = [[
            'service_id' => $activeId,
            'display_order' => 1,
            'is_featured' => true,
        ]];

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame($activeId, $saved['sections'][2]['items'][0]['service_id']);
        self::assertSame(1, $saved['sections'][2]['items'][0]['display_order']);
        self::assertTrue($saved['sections'][2]['items'][0]['is_featured']);

        $saved['sections'][2]['items'][0]['service_id'] = $inactiveId;
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected an inactive Service reference to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    public function test_doctor_profiles_support_repeatable_ordered_content_and_invalid_names_do_not_advance_version(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $firstId = '00000000-0000-4000-8000-000000000050';
        $secondId = '00000000-0000-4000-8000-000000000051';
        $sections[3]['profiles'] = [
            [
                'id' => $secondId,
                'name' => 'Dr Second',
                'professional_title' => 'Family Medicine',
                'visible' => false,
                'photo_asset_id' => null,
            ],
            [
                'id' => $firstId,
                'name' => 'Dr First',
                'professional_title' => null,
                'visible' => true,
                'photo_asset_id' => null,
            ],
        ];

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame([$secondId, $firstId], array_column($saved['sections'][3]['profiles'], 'id'));
        self::assertFalse($saved['sections'][3]['profiles'][0]['visible']);
        self::assertSame('Family Medicine', $saved['sections'][3]['profiles'][0]['professional_title']);

        $saved['sections'][3]['profiles'][0]['name'] = '';
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected an invalid Doctor profile to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    public function test_gallery_supports_ordered_existing_asset_references_and_rejects_invalid_items(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $firstAsset = '00000000-0000-4000-8000-000000000060';
        $secondAsset = '00000000-0000-4000-8000-000000000061';
        $sections[5]['images'] = [
            [
                'id' => '00000000-0000-4000-8000-000000000062',
                'asset_id' => $secondAsset,
                'alt_text' => 'Consultation room',
                'caption' => 'A comfortable consultation room.',
                'decorative' => false,
            ],
            [
                'id' => '00000000-0000-4000-8000-000000000063',
                'asset_id' => $firstAsset,
                'alt_text' => null,
                'caption' => null,
                'decorative' => true,
            ],
        ];

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame(
            [$secondAsset, $firstAsset],
            array_column($saved['sections'][5]['images'], 'asset_id'),
        );
        self::assertTrue($saved['sections'][5]['images'][1]['decorative']);

        $saved['sections'][5]['images'][1]['alt_text'] = 'Invalid decorative alt text';
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected an invalid Gallery image to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    public function test_testimonials_support_repeatable_ordered_content_and_invalid_values_do_not_advance_version(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $firstId = '00000000-0000-4000-8000-000000000070';
        $secondId = '00000000-0000-4000-8000-000000000071';
        $sections[4]['testimonials'] = [
            [
                'id' => $secondId,
                'quote' => 'The team made every visit comfortable.',
                'author_name' => 'Patient Two',
                'featured' => false,
            ],
            [
                'id' => $firstId,
                'quote' => 'Professional and compassionate care.',
                'author_name' => 'Patient One',
                'featured' => true,
            ],
        ];

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame(
            [$secondId, $firstId],
            array_column($saved['sections'][4]['testimonials'], 'id'),
        );
        self::assertTrue($saved['sections'][4]['testimonials'][1]['featured']);

        $saved['sections'][4]['testimonials'][0]['quote'] = '';
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected an invalid Testimonial to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    public function test_faq_supports_repeatable_ordered_entries_and_required_values_do_not_advance_version(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $firstId = '00000000-0000-4000-8000-000000000080';
        $secondId = '00000000-0000-4000-8000-000000000081';
        $sections[6]['entries'] = [
            [
                'id' => $secondId,
                'question' => 'Do I need an appointment?',
                'answer' => 'Appointments are recommended.',
            ],
            [
                'id' => $firstId,
                'question' => 'When are you open?',
                'answer' => 'We are open every weekday.',
            ],
        ];

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame(
            [$secondId, $firstId],
            array_column($saved['sections'][6]['entries'], 'id'),
        );
        self::assertSame(
            'Appointments are recommended.',
            $saved['sections'][6]['entries'][0]['answer'],
        );

        $saved['sections'][6]['entries'][0]['question'] = '';
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected an invalid FAQ entry to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    public function test_booking_cta_updates_only_existing_optional_fields_and_invalid_values_do_not_advance_version(): void
    {
        $repository = new InMemoryWebsiteDraftRepository($this->draft());
        $codec = new WebsiteDraftSectionCodec;
        $service = new ManageWebsiteDraftContentService(
            $repository,
            new WebsiteAuthorization,
            $codec,
            new FixedActiveServiceReferences,
        );
        $authorization = new WebsiteAuthorizationContext(
            self::DESIGNER,
            'website_designer',
            assignedTenantId: self::TENANT,
        );
        $sections = $service->load(
            new LoadDraftWebsiteContent($authorization, self::TENANT, self::WEBSITE),
        )->toArray()['sections'];
        $heroId = $sections[0]['section_id'];
        $sections[8]['heading'] = 'Ready to book an appointment?';
        $sections[8]['description'] = 'Choose a suitable service and appointment time.';
        $sections[8]['button_label'] = 'Book now';

        $saved = $service->save(new SaveDraftWebsiteContent(
            $authorization,
            self::TENANT,
            self::WEBSITE,
            1,
            $sections,
        ))->toArray();

        self::assertSame(2, $saved['version']);
        self::assertSame('Book now', $saved['sections'][8]['button_label']);
        self::assertSame($heroId, $saved['sections'][0]['section_id']);
        self::assertCount(9, $saved['sections']);

        $saved['sections'][8]['button_label'] = str_repeat('x', 81);
        try {
            $service->save(new SaveDraftWebsiteContent(
                $authorization,
                self::TENANT,
                self::WEBSITE,
                2,
                $saved['sections'],
            ));
            self::fail('Expected an invalid Booking CTA to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $repository->find(
                new TenantId(self::TENANT),
                new WebsiteId(self::WEBSITE),
            )?->version);
        }
    }

    private function draft(): WebsiteDraftContent
    {
        $section = static fn (int $number): SectionId => new SectionId(sprintf(
            '00000000-0000-4000-8000-%012d',
            $number,
        ));

        return new WebsiteDraftContent(
            new WebsiteId(self::WEBSITE),
            new TenantId(self::TENANT),
            1,
            [
                new HeroSectionContent($section(11)),
                new AboutSectionContent($section(12)),
                new ServicesSectionContent($section(13)),
                new DoctorsSectionContent($section(14)),
                new TestimonialsSectionContent($section(15)),
                new GallerySectionContent($section(16)),
                new FaqSectionContent($section(17)),
                new ContactSectionContent($section(18)),
                new BookingCtaSectionContent($section(19)),
            ],
        );
    }
}

final readonly class FixedActiveServiceReferences implements ActiveServiceReferenceReadInterface
{
    /** @param list<string> $references */
    public function __construct(private array $references = []) {}

    public function forTenant(string $tenantId): array
    {
        return $this->references;
    }
}

final class InMemoryWebsiteDraftRepository implements WebsiteDraftRepositoryInterface
{
    public function __construct(private WebsiteDraftContent $draft) {}

    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
    {
        if ($tenantId->value !== $this->draft->tenantId->value
            || $websiteId->value !== $this->draft->websiteId->value) {
            return null;
        }

        return $this->draft;
    }

    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent
    {
        $this->draft = new WebsiteDraftContent(
            $draft->websiteId,
            $draft->tenantId,
            $expectedVersion + 1,
            $draft->sections,
        );

        return $this->draft;
    }
}
