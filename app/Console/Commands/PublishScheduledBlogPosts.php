<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Blog\Application\BlogAuthorization;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PublishScheduledBlogPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Idempotently publishes eligible scheduled Blog posts';

    public function handle(ConnectionInterface $connection, BlogAuthorization $authorization): int
    {
        $posts = $connection->table('blog_posts')->where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get();
        foreach ($posts as $post) {
            if (! $authorization->entitled((string) $post->tenant_id)) {
                continue;
            }
            $connection->transaction(function () use ($connection, $post): void {
                $now = now();
                $version = (int) $post->version + 1;
                if ($connection->table('blog_posts')->where('id', $post->id)->where('version', $post->version)->where('status', 'scheduled')->update(['status' => 'published', 'published_at' => $now, 'scheduled_at' => null, 'last_changed_at' => $now, 'updated_at' => $now, 'version' => $version]) !== 1) {
                    return;
                }
                $fresh = $connection->table('blog_posts')->where('id', $post->id)->first();
                $connection->table('blog_post_publications')->insertOrIgnore(['id' => (string) Str::uuid(), 'blog_post_id' => $post->id, 'tenant_id' => $post->tenant_id, 'website_id' => $post->website_id, 'source_version' => $version, 'snapshot' => json_encode((array) $fresh, JSON_THROW_ON_ERROR), 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                $connection->table('blog_post_audits')->insert(['blog_post_id' => $post->id, 'tenant_id' => $post->tenant_id, 'actor_identity_id' => '00000000-0000-4000-8000-000000000001', 'actor_role' => 'super_admin', 'action' => 'scheduled_publish', 'post_version' => $version, 'metadata' => '{}', 'occurred_at' => $now]);
            });
        }
        $this->info('Scheduled Blog publication pass completed.');

        return self::SUCCESS;
    }
}
