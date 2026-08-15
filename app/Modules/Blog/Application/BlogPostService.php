<?php

declare(strict_types=1);

namespace App\Modules\Blog\Application;

use App\Modules\Blog\Infrastructure\BlogPostRecord;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class BlogPostService
{
    public function __construct(
        private ConnectionInterface $connection,
        private BlogAuthorization $authorization,
        private BlogContentSanitizer $sanitizer,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(AuthorizationContext $actor, string $tenantId, string $websiteId, array $input): string
    {
        $this->authorization->authorize($actor, $tenantId, $websiteId);
        $this->assertFeaturedImageOwnership($input['featured_image_asset_id'] ?? null, $tenantId, $websiteId);
        $id = (string) Str::uuid();
        $now = now();
        $values = $this->content($input);
        $this->connection->transaction(function () use ($id, $tenantId, $websiteId, $actor, $now, $values): void {
            $this->connection->table('blog_posts')->insert(array_merge($values, [
                'id' => $id, 'tenant_id' => $tenantId, 'website_id' => $websiteId,
                'author_identity_id' => $actor->identityId, 'author_role' => $actor->role,
                'author_name' => $actor->name ?? 'Pasukan klinik', 'status' => 'draft',
                'created_at_domain' => $now, 'last_changed_at' => $now, 'version' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]));
            $this->audit($id, $tenantId, $actor, 'create', 1);
        });

        return $id;
    }

    /** @param array<string, mixed> $input */
    public function update(AuthorizationContext $actor, string $id, int $expectedVersion, array $input): void
    {
        $post = $this->post($id);
        $this->authorization->authorize($actor, (string) $post->tenant_id, (string) $post->website_id);
        $this->assertFeaturedImageOwnership($input['featured_image_asset_id'] ?? null, $post->tenant_id, $post->website_id);
        $values = array_merge($this->content($input), ['last_changed_at' => now(), 'updated_at' => now(), 'version' => $expectedVersion + 1]);
        $changed = $this->connection->table('blog_posts')->where('id', $id)->where('version', $expectedVersion)->update($values);
        if ($changed !== 1) {
            throw new ConflictHttpException('Artikel telah berubah. Muat semula sebelum menyimpan semula.');
        }
        $this->audit($id, (string) $post->tenant_id, $actor, 'update', $expectedVersion + 1);
    }

    public function transition(AuthorizationContext $actor, string $id, int $expectedVersion, string $action, ?string $scheduledAt = null): void
    {
        $post = $this->post($id);
        $this->authorization->authorize($actor, (string) $post->tenant_id, (string) $post->website_id);
        if ($actor->role === 'super_admin' && $action !== 'archive') {
            abort(403, 'Super Admin hanya dibenarkan menjalankan tindakan pentadbiran archive pada artikel.');
        }
        [$from, $to] = match ($action) {
            'submit_review' => [['draft', 'correction_required'], 'in_review'],
            'correction' => [['in_review'], 'correction_required'],
            'publish' => [['draft', 'in_review', 'correction_required', 'scheduled', 'published'], 'published'],
            'schedule' => [['draft', 'in_review', 'correction_required'], 'scheduled'],
            'archive' => [['draft', 'in_review', 'correction_required', 'scheduled', 'published'], 'archived'],
            default => abort(422, 'Tindakan artikel tidak sah.'),
        };
        if (! in_array((string) $post->status, $from, true)) {
            throw ValidationException::withMessages([
                'action' => 'Tindakan ini tidak tersedia untuk status artikel semasa.',
            ]);
        }
        if (in_array($action, ['publish', 'schedule'], true) && ! $this->authorization->entitled((string) $post->tenant_id)) {
            abort(403, 'Penerbitan Blog memerlukan entitlement Syifa Standard yang aktif.');
        }
        $now = now();
        $values = ['status' => $to, 'last_changed_at' => $now, 'updated_at' => $now, 'version' => $expectedVersion + 1];
        if ($action === 'schedule') {
            abort_if($scheduledAt === null || strtotime($scheduledAt) <= time(), 422, 'Tarikh jadual mesti pada masa hadapan.');
            $values['scheduled_at'] = $scheduledAt;
        }
        if ($action === 'publish') {
            $values['published_at'] = $now;
            $values['scheduled_at'] = null;
        }
        $this->connection->transaction(function () use ($post, $id, $expectedVersion, $values, $action, $actor, $now): void {
            $changed = $this->connection->table('blog_posts')->where('id', $id)->where('version', $expectedVersion)->update($values);
            if ($changed !== 1) {
                throw new ConflictHttpException('Artikel telah berubah. Muat semula sebelum meneruskan.');
            }
            if ($action === 'publish') {
                $fresh = $this->connection->table('blog_posts')->where('id', $id)->first();
                abort_if($fresh === null, 404);
                $this->connection->table('blog_post_publications')->where('blog_post_id', $id)->whereNull('withdrawn_at')->update(['withdrawn_at' => $now, 'updated_at' => $now]);
                $this->connection->table('blog_post_publications')->insert([
                    'id' => (string) Str::uuid(), 'blog_post_id' => $id, 'tenant_id' => $post->tenant_id,
                    'website_id' => $post->website_id, 'source_version' => $expectedVersion + 1,
                    'snapshot' => json_encode((array) $fresh, JSON_THROW_ON_ERROR), 'published_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            if ($action === 'archive') {
                $this->connection->table('blog_post_publications')->where('blog_post_id', $id)->whereNull('withdrawn_at')->update(['withdrawn_at' => $now, 'updated_at' => $now]);
            }
            $this->audit($id, (string) $post->tenant_id, $actor, $action, $expectedVersion + 1);
        });
    }

    private function post(string $id): BlogPostRecord
    {
        $row = $this->connection->table('blog_posts')->where('id', $id)->first();
        abort_if($row === null, 404);

        return BlogPostRecord::fromDatabase($row);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function content(array $input): array
    {
        return [
            'title' => trim((string) $input['title']), 'slug' => Str::slug((string) $input['slug']),
            'excerpt' => trim((string) $input['excerpt']), 'body_html' => $this->sanitizer->sanitize((string) $input['body']),
            'featured_image_asset_id' => $input['featured_image_asset_id'] ?? null,
            'featured_image_alt_text' => isset($input['featured_image_alt_text']) ? trim((string) $input['featured_image_alt_text']) : null,
            'category' => trim((string) $input['category']),
            'tags' => json_encode(array_values(array_unique($input['tags'] ?? [])), JSON_THROW_ON_ERROR),
            'meta_title' => trim((string) $input['meta_title']), 'meta_description' => trim((string) $input['meta_description']),
            'canonical_url' => $input['canonical_url'] ?? null, 'robots_directive' => (string) $input['robots_directive'],
            'open_graph_title' => trim((string) $input['open_graph_title']),
            'open_graph_description' => trim((string) $input['open_graph_description']),
        ];
    }

    private function audit(string $id, string $tenantId, AuthorizationContext $actor, string $action, int $version): void
    {
        $this->connection->table('blog_post_audits')->insert([
            'blog_post_id' => $id, 'tenant_id' => $tenantId, 'actor_identity_id' => $actor->identityId,
            'actor_role' => $actor->role, 'action' => $action, 'post_version' => $version,
            'metadata' => '{}', 'occurred_at' => now(),
        ]);
    }

    private function assertFeaturedImageOwnership(mixed $assetId, string $tenantId, string $websiteId): void
    {
        if ($assetId === null || $assetId === '') {
            return;
        }

        $owned = $this->connection->table('website_assets')
            ->where('id', (string) $assetId)
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('status', 'available')
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->where('file_size_bytes', '<=', 5 * 1024 * 1024)
            ->whereNotNull('width')
            ->whereNotNull('height')
            ->exists();

        abort_unless($owned, 422, 'Imej utama mesti merupakan imej tersedia milik website klinik yang sama dan tidak melebihi 5 MB.');
    }
}
