<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Blog;

use App\Console\Commands\PublishScheduledBlogPosts;
use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\Blog\Application\BlogContentSanitizer;
use App\Modules\Blog\Application\BlogPostService;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class BlogPostWorkflowTest extends TestCase
{
    private ConnectionInterface $connection;

    private TestBlogEntitlements $entitlements;

    private BlogPostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = DB::connection();
        $this->schema();
        $this->entitlements = new TestBlogEntitlements;
        $authorization = new BlogAuthorization($this->entitlements, $this->connection);
        $this->service = new BlogPostService($this->connection, $authorization, new BlogContentSanitizer);
        $this->website($this->uuid(1), $this->uuid(11));
        $this->website($this->uuid(2), $this->uuid(22));
    }

    public function test_standard_owner_creates_sanitized_draft_with_audit(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $id = $this->service->create($this->owner($this->uuid(1)), $this->uuid(1), $this->uuid(11), $this->content([
            'body' => '<h2 onclick="bad()">Panduan</h2><script>alert(1)</script><p>Selamat</p>',
        ]));

        $post = $this->connection->table('blog_posts')->where('id', $id)->first();
        self::assertSame('draft', $post?->status);
        self::assertSame('<h2>Panduan</h2><p>Selamat</p>', $post?->body_html);
        self::assertSame(1, $post?->version);
        self::assertSame('create', $this->connection->table('blog_post_audits')->value('action'));
    }

    public function test_trial_or_basic_backend_access_fails_closed(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        $this->service->create($this->owner($this->uuid(1)), $this->uuid(1), $this->uuid(11), $this->content());
    }

    public function test_owner_cannot_create_for_another_tenant(): void
    {
        $this->entitlements->allow($this->uuid(2));
        try {
            $this->service->create($this->owner($this->uuid(1)), $this->uuid(2), $this->uuid(22), $this->content());
            self::fail('Cross-tenant mutation should be denied.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_designer_requires_an_active_assignment_for_the_exact_website(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $designer = new AuthorizationContext('platform_identity', $this->uuid(91), null, 'website_designer', 'Pereka', 'workforce', []);
        $this->connection->table('onboarding_jobs')->insert(['id' => $this->uuid(81), 'tenant_id' => $this->uuid(1), 'website_id' => $this->uuid(11)]);
        $this->connection->table('website_designer_assignments')->insert(['id' => $this->uuid(82), 'onboarding_job_id' => $this->uuid(81), 'tenant_id' => $this->uuid(1), 'platform_identity_id' => $this->uuid(91), 'assignment_status' => 'active']);

        $id = $this->service->create($designer, $this->uuid(1), $this->uuid(11), $this->content());
        self::assertNotSame('', $id);
        try {
            $this->service->create($designer, $this->uuid(2), $this->uuid(22), $this->content(['slug' => 'tenant-lain']));
            self::fail('Unassigned Website should be denied.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_update_uses_optimistic_locking(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content());
        $this->service->update($owner, $id, 1, $this->content(['title' => 'Versi dua']));
        self::assertSame(2, $this->connection->table('blog_posts')->where('id', $id)->value('version'));

        $this->expectException(ConflictHttpException::class);
        $this->service->update($owner, $id, 1, $this->content(['title' => 'Penulisan lapuk']));
    }

    public function test_publish_uses_immutable_snapshot_and_archive_withdraws_it(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content(['title' => 'Versi diterbitkan']));
        $this->service->transition($owner, $id, 1, 'submit_review');
        $this->service->transition($owner, $id, 2, 'publish');
        $snapshot = (string) $this->connection->table('blog_post_publications')->value('snapshot');
        self::assertStringContainsString('Versi diterbitkan', $snapshot);

        $this->service->update($owner, $id, 3, $this->content(['title' => 'Perubahan selepas publish']));
        self::assertSame($snapshot, $this->connection->table('blog_post_publications')->value('snapshot'));
        $this->service->transition($owner, $id, 4, 'archive');
        self::assertNotNull($this->connection->table('blog_post_publications')->value('withdrawn_at'));
    }

    public function test_invalid_status_transition_returns_a_form_validation_error(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content());
        $this->service->transition($owner, $id, 1, 'publish');

        try {
            $this->service->transition($owner, $id, 2, 'submit_review');
            self::fail('A published article must not return to review.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'Tindakan ini tidak tersedia untuk status artikel semasa.',
                $exception->errors()['action'][0] ?? null,
            );
        }

        self::assertSame('published', $this->connection->table('blog_posts')->where('id', $id)->value('status'));
        self::assertSame(2, $this->connection->table('blog_posts')->where('id', $id)->value('version'));
    }

    public function test_published_article_can_be_republished_with_a_new_immutable_snapshot(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content());
        $this->service->transition($owner, $id, 1, 'publish');
        $this->service->update($owner, $id, 2, $this->content(['title' => 'Artikel dikemas kini']));
        $this->service->transition($owner, $id, 3, 'publish');

        self::assertSame(2, $this->connection->table('blog_post_publications')->count());
        self::assertSame(1, $this->connection->table('blog_post_publications')->whereNull('withdrawn_at')->count());
        self::assertStringContainsString('Artikel dikemas kini', (string) $this->connection->table('blog_post_publications')->whereNull('withdrawn_at')->value('snapshot'));
    }

    public function test_downgrade_keeps_posts_but_blocks_further_publication(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content());
        $this->entitlements->deny($this->uuid(1));

        try {
            $this->service->transition($owner, $id, 1, 'publish');
            self::fail('Downgraded tenant should not publish.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        self::assertSame(1, $this->connection->table('blog_posts')->where('id', $id)->count());
    }

    public function test_featured_image_must_belong_to_same_tenant_and_website(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $asset = $this->uuid(71);
        $this->connection->table('website_assets')->insert(['id' => $asset, 'tenant_id' => $this->uuid(2), 'website_id' => $this->uuid(22), 'status' => 'available', 'mime_type' => 'image/webp', 'file_size_bytes' => 1000, 'width' => 1200, 'height' => 630]);

        try {
            $this->service->create($this->owner($this->uuid(1)), $this->uuid(1), $this->uuid(11), $this->content(['featured_image_asset_id' => $asset, 'featured_image_alt_text' => 'Imej']));
            self::fail('Cross-tenant asset should be denied.');
        } catch (HttpException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_super_admin_may_archive_but_cannot_publish_or_edit_content(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content());
        $admin = new AuthorizationContext('platform_identity', $this->uuid(92), null, 'super_admin', 'Admin', 'workforce', []);

        try {
            $this->service->transition($admin, $id, 1, 'publish');
            self::fail('Super Admin must not publish editorial content.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $this->service->transition($admin, $id, 1, 'archive');
        self::assertSame('archived', $this->connection->table('blog_posts')->where('id', $id)->value('status'));
        self::assertSame('archive', $this->connection->table('blog_post_audits')->orderByDesc('sequence')->value('action'));
    }

    public function test_scheduled_publisher_is_idempotent_and_records_snapshot(): void
    {
        $this->entitlements->allow($this->uuid(1));
        $owner = $this->owner($this->uuid(1));
        $id = $this->service->create($owner, $this->uuid(1), $this->uuid(11), $this->content());
        $this->connection->table('blog_posts')->where('id', $id)->update(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
        $authorization = new BlogAuthorization($this->entitlements, $this->connection);
        app()->instance(BlogAuthorization::class, $authorization);
        $command = app(PublishScheduledBlogPosts::class);
        $command->setLaravel(app());
        $command->run(new ArrayInput([]), new BufferedOutput);

        self::assertSame('published', $this->connection->table('blog_posts')->where('id', $id)->value('status'));
        self::assertSame(1, $this->connection->table('blog_post_publications')->count());

        $command->handle($this->connection, $authorization);
        self::assertSame(1, $this->connection->table('blog_post_publications')->count());
    }

    /** @param array<string, mixed> $override @return array<string, mixed> */
    private function content(array $override = []): array
    {
        return array_merge(['title' => 'Panduan Kesihatan', 'slug' => 'panduan-kesihatan', 'excerpt' => 'Ringkasan artikel.', 'body' => '<p>Kandungan artikel.</p>', 'featured_image_asset_id' => null, 'featured_image_alt_text' => null, 'category' => 'Kesihatan', 'tags' => ['panduan'], 'meta_title' => 'Panduan Kesihatan Klinik', 'meta_description' => 'Panduan kesihatan daripada pasukan klinik untuk bacaan umum.', 'canonical_url' => null, 'robots_directive' => 'index,follow', 'open_graph_title' => 'Panduan Kesihatan Klinik', 'open_graph_description' => 'Panduan kesihatan daripada pasukan klinik.'], $override);
    }

    private function owner(string $tenantId): AuthorizationContext
    {
        return new AuthorizationContext('clinic_owner_authority', $this->uuid(90), $tenantId, 'clinic_owner', 'Pemilik', 'clinic', []);
    }

    private function website(string $tenantId, string $websiteId): void
    {
        $this->connection->table('websites')->insert(['id' => $websiteId, 'tenant_id' => $tenantId]);
    }

    private function schema(): void
    {
        Schema::create('websites', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id')->unique()]);
        Schema::create('website_assets', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id'), $t->uuid('website_id'), $t->string('status'), $t->string('mime_type'), $t->unsignedBigInteger('file_size_bytes'), $t->unsignedInteger('width')->nullable(), $t->unsignedInteger('height')->nullable()]);
        Schema::create('onboarding_jobs', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id'), $t->uuid('website_id')]);
        Schema::create('website_designer_assignments', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('onboarding_job_id'), $t->uuid('tenant_id'), $t->uuid('platform_identity_id'), $t->string('assignment_status')]);
        Schema::create('blog_posts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id');
            $t->uuid('website_id');
            $t->uuid('author_identity_id');
            $t->string('author_role');
            $t->string('author_name');
            $t->string('title');
            $t->string('slug');
            $t->text('excerpt');
            $t->text('body_html');
            $t->uuid('featured_image_asset_id')->nullable();
            $t->string('featured_image_alt_text')->nullable();
            $t->string('category');
            $t->json('tags');
            $t->string('status');
            $t->string('meta_title');
            $t->string('meta_description');
            $t->string('canonical_url')->nullable();
            $t->string('robots_directive');
            $t->string('open_graph_title');
            $t->string('open_graph_description');
            $t->timestamp('published_at')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('created_at_domain');
            $t->timestamp('last_changed_at');
            $t->unsignedBigInteger('version');
            $t->timestamps();
            $t->unique(['website_id', 'slug']);
        });
        Schema::create('blog_post_publications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('blog_post_id');
            $t->uuid('tenant_id');
            $t->uuid('website_id');
            $t->unsignedBigInteger('source_version');
            $t->json('snapshot');
            $t->timestamp('published_at');
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamps();
        });
        Schema::create('blog_post_audits', function (Blueprint $t): void {
            $t->increments('sequence');
            $t->uuid('blog_post_id');
            $t->uuid('tenant_id');
            $t->uuid('actor_identity_id');
            $t->string('actor_role');
            $t->string('action');
            $t->unsignedBigInteger('post_version');
            $t->json('metadata');
            $t->uuid('correlation_id')->nullable();
            $t->timestamp('occurred_at');
        });
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class TestBlogEntitlements implements SubscriptionEntitlementLookupInterface
{
    /** @var array<string, true> */
    private array $allowed = [];

    public function allow(string $tenantId): void
    {
        $this->allowed[$tenantId] = true;
    }

    public function deny(string $tenantId): void
    {
        unset($this->allowed[$tenantId]);
    }

    public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
    {
        return $capabilityKey === 'website.blog.manage' && isset($this->allowed[$tenantId]);
    }

    public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
    {
        return isset($this->allowed[$tenantId]) ? ['website.blog.manage'] : [];
    }
}
