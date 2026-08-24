<?php

declare(strict_types=1);

namespace App\Modules\Blog\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\Blog\Application\BlogPostService;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\WebsiteDesignerDashboardNavigation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

final readonly class BlogDashboardController
{
    public function __construct(private ConnectionInterface $connection, private BlogAuthorization $authorization) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        abort_if($actor->role === 'website_designer', 403);

        return $this->renderIndex($request, $actor);
    }

    public function designerIndex(Request $request, string $jobId): Response
    {
        $actor = $this->actor($request);

        return $this->renderIndex($request, $actor, $this->assignedJob($actor, $jobId));
    }

    private function renderIndex(Request $request, AuthorizationContext $actor, ?stdClass $job = null): Response
    {
        $query = $this->connection->table('blog_posts as post')->join('websites as website', 'website.id', '=', 'post.website_id');
        if ($actor->role === 'clinic_owner') {
            abort_if($actor->tenantId === null, 403);
            $query->where('post.tenant_id', $actor->tenantId);
        } elseif ($job !== null) {
            $query->where('post.tenant_id', (string) $job->tenant_id)
                ->where('post.website_id', (string) $job->website_id);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('post.title', 'ilike', "%{$search}%")->orWhere('post.excerpt', 'ilike', "%{$search}%"));
        }
        if ($status = $request->query('status')) {
            $query->where('post.status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('post.category', $category);
        }
        $posts = $query->select('post.*', 'website.clinic_name')->orderByDesc('post.last_changed_at')->paginate(15)->withQueryString();
        $tenantId = $job === null ? $actor->tenantId : (string) $job->tenant_id;
        $entitled = $actor->role === 'super_admin' || ($tenantId !== null && $this->authorization->entitled($tenantId));
        $urls = $this->urls($job);

        return Inertia::render('Blog/BlogIndex', [
            ...$this->shell($actor, false, $job),
            'posts' => $posts,
            'entitled' => $entitled,
            'filters' => $request->only(['search', 'status', 'category']),
            'summary' => collect($posts->items())->countBy('status'),
            'role' => $actor->role,
            'clinicName' => $job?->clinic_name,
            'indexUrl' => $urls['index'],
            'createUrl' => $urls['create'],
            'showUpgrade' => $actor->role === 'clinic_owner' && ! $entitled,
        ]);
    }

    public function editor(Request $request, ?string $postId = null): Response
    {
        $actor = $this->actor($request);
        abort_if($actor->role === 'website_designer', 403);

        return $this->renderEditor($actor, $postId);
    }

    public function designerEditor(Request $request, string $jobId, ?string $postId = null): Response
    {
        $actor = $this->actor($request);

        return $this->renderEditor($actor, $postId, $this->assignedJob($actor, $jobId));
    }

    private function renderEditor(AuthorizationContext $actor, ?string $postId, ?stdClass $job = null): Response
    {
        $post = $postId === null ? null : $this->connection->table('blog_posts')->where('id', $postId)->first();
        if ($job !== null && $post !== null) {
            $this->assertPostBelongsToJob($post, $job);
        }
        if ($post !== null) {
            $this->authorization->authorize($actor, (string) $post->tenant_id, (string) $post->website_id);
        } elseif ($job !== null) {
            abort_unless($this->authorization->entitled((string) $job->tenant_id), 403, 'Blog memerlukan entitlement Syifa Pro yang aktif.');
        } elseif ($actor->role === 'clinic_owner') {
            abort_if($actor->tenantId === null || ! $this->authorization->entitled($actor->tenantId), 403, 'Blog memerlukan entitlement Syifa Pro yang aktif.');
        }

        $websites = $job === null ? [] : [[
            'id' => (string) $job->website_id,
            'tenant_id' => (string) $job->tenant_id,
            'clinic_name' => (string) $job->clinic_name,
            'upload_url' => route('website-designer.website-assets.store', (string) $job->id),
        ]];
        $mediaUploadUrl = $actor->role === 'clinic_owner'
            ? route('clinic-owner.website-assets.store')
            : ($job === null ? null : route('website-designer.website-assets.store', (string) $job->id));
        $urls = $this->urls($job, $postId);

        return Inertia::render('Blog/BlogEditor', [
            ...$this->shell($actor, true, $job),
            'post' => $post,
            'role' => $actor->role,
            'websites' => $websites,
            'mediaUploadUrl' => $mediaUploadUrl,
            'assetUrlTemplate' => route('public-website.assets.show', '__ASSET_ID__'),
            'clinicName' => $job?->clinic_name,
            'indexUrl' => $urls['index'],
            'storeUrl' => $urls['store'],
            'updateUrl' => $urls['update'],
            'transitionUrl' => $urls['transition'],
            'canEdit' => $actor->role === 'clinic_owner'
                || ($actor->role === 'website_designer' && ($post === null || in_array((string) $post->status, ['draft', 'correction_required'], true))),
        ]);
    }

    public function store(Request $request, BlogPostService $service): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_if($actor->role === 'website_designer', 403);
        $website = $this->connection->table('websites')->where('tenant_id', $actor->tenantId)->first(['id', 'tenant_id']);
        abort_if($website === null, 404);
        $id = $service->create($actor, (string) $website->tenant_id, (string) $website->id, $this->validate($request));

        return redirect()->route('dashboard.blog.edit', $id)->with('success', 'Draf artikel disimpan.');
    }

    public function designerStore(Request $request, string $jobId, BlogPostService $service): RedirectResponse
    {
        $actor = $this->actor($request);
        $job = $this->assignedJob($actor, $jobId);
        $id = $service->create($actor, (string) $job->tenant_id, (string) $job->website_id, $this->validate($request));

        return redirect()->route('dashboard.onboarding.blog.edit', ['jobId' => $jobId, 'postId' => $id])
            ->with('success', 'Draf artikel disimpan.');
    }

    public function update(Request $request, string $postId, BlogPostService $service): RedirectResponse
    {
        $data = $this->validate($request);
        $service->update($this->actor($request), $postId, (int) $request->validate(['version' => ['required', 'integer', 'min:1']])['version'], $data);

        return back()->with('success', 'Artikel dikemas kini.');
    }

    public function designerUpdate(Request $request, string $jobId, string $postId, BlogPostService $service): RedirectResponse
    {
        $actor = $this->actor($request);
        $job = $this->assignedJob($actor, $jobId);
        $this->assertPostBelongsToJob($this->post($postId), $job);
        $data = $this->validate($request);
        $service->update($actor, $postId, (int) $request->validate(['version' => ['required', 'integer', 'min:1']])['version'], $data);

        return back()->with('success', 'Artikel dikemas kini.');
    }

    public function transition(Request $request, string $postId, BlogPostService $service): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['submit_review', 'correction', 'publish', 'schedule', 'archive'])], 'scheduled_at' => ['nullable', 'date']]);
        $service->transition($this->actor($request), $postId, (int) $data['version'], (string) $data['action'], $data['scheduled_at'] ?? null);

        return back()->with('success', 'Status artikel dikemas kini.');
    }

    public function designerTransition(Request $request, string $jobId, string $postId, BlogPostService $service): RedirectResponse
    {
        $actor = $this->actor($request);
        $job = $this->assignedJob($actor, $jobId);
        $this->assertPostBelongsToJob($this->post($postId), $job);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['submit_review'])],
        ]);
        $service->transition($actor, $postId, (int) $data['version'], (string) $data['action']);

        return back()->with('success', 'Draf dihantar kepada Clinic Owner untuk semakan.');
    }

    /** @return array<string, mixed> */
    private function validate(Request $request): array
    {
        // The dashboard only offers a plain text field for canonical_url -
        // typing "clinic.example/article" instead of
        // "https://clinic.example/article" is a scheme omission, not an
        // invalid URL, so it's corrected before the `url:https` rule below
        // would otherwise reject it outright.
        $request->merge(['canonical_url' => self::withHttpsScheme($request->input('canonical_url'))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'excerpt' => ['required', 'string', 'max:600'], 'body' => ['required', 'string', 'max:100000'],
            'featured_image_asset_id' => ['nullable', 'uuid', 'exists:website_assets,id'],
            'featured_image_alt_text' => ['nullable', 'required_with:featured_image_asset_id', 'string', 'max:240'],
            'category' => ['required', 'string', 'max:100'], 'tags' => ['array', 'max:12'], 'tags.*' => ['string', 'max:50'],
            'meta_title' => ['nullable', 'string', 'max:60'], 'meta_description' => ['nullable', 'string', 'max:160'],
            'canonical_url' => ['nullable', 'url:https', 'max:2048'], 'robots_directive' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
            'open_graph_title' => ['nullable', 'string', 'max:60'], 'open_graph_description' => ['nullable', 'string', 'max:200'],
        ]);

        $title = trim((string) $data['title']);
        $excerpt = trim((string) $data['excerpt']);
        $data['meta_title'] = trim((string) ($data['meta_title'] ?? '')) ?: Str::limit($title, 60, '');
        $data['meta_description'] = trim((string) ($data['meta_description'] ?? '')) ?: Str::limit($excerpt, 160, '');
        // There is no dashboard field to set these independently, so they
        // always mirror the meta title/derived excerpt rather than only
        // defaulting once when empty - otherwise they freeze at whatever
        // they were on creation and drift stale as the post is edited.
        $data['open_graph_title'] = $data['meta_title'];
        $data['open_graph_description'] = Str::limit($excerpt, 200, '');
        $data['robots_directive'] = $data['robots_directive'] ?? 'index,follow';

        return $data;
    }

    /**
     * Only adds a scheme when one is entirely missing - an explicit
     * "http://" is left alone (and still rejected by the `url:https` rule
     * above) rather than silently upgraded, since there's no way to know
     * the target actually serves that URL over TLS.
     */
    private static function withHttpsScheme(mixed $url): mixed
    {
        if (! is_string($url)) {
            return $url;
        }
        $trimmed = trim($url);
        if ($trimmed === '' || preg_match('#^https?://#i', $trimmed) === 1) {
            return $trimmed;
        }

        return 'https://'.$trimmed;
    }

    private function actor(Request $request): AuthorizationContext
    {
        $actor = $request->attributes->get(AuthorizationContext::class);
        abort_unless($actor instanceof AuthorizationContext, 403);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function shell(AuthorizationContext $actor, bool $editor, ?stdClass $job = null): array
    {
        $navigation = match ($actor->role) {
            'clinic_owner' => ClinicOwnerDashboardNavigation::items('blog'),
            'website_designer' => WebsiteDesignerDashboardNavigation::items('onboarding'),
            'super_admin' => $this->superAdminNavigation(),
            default => [],
        };

        $breadcrumbs = [['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')]];
        if ($job !== null) {
            $breadcrumbs[] = ['key' => 'onboarding', 'label' => 'Onboarding', 'href' => route('dashboard.onboarding')];
            $breadcrumbs[] = ['key' => 'job', 'label' => (string) $job->clinic_name, 'href' => route('dashboard.onboarding.show', (string) $job->id)];
        }
        $breadcrumbs[] = [
            'key' => 'blog',
            'label' => 'Blog',
            'href' => $editor ? $this->urls($job)['index'] : null,
        ];
        if ($editor) {
            $breadcrumbs[] = ['key' => 'editor', 'label' => 'Editor artikel'];
        }

        return [
            'navigation' => $navigation,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $editor ? 'Editor artikel' : 'Blog',
            'pageDescription' => $editor
                ? 'Tulis artikel dengan panduan mudah. Tetapan carian disediakan secara automatik.'
                : 'Urus artikel kesihatan, semakan dan penerbitan website klinik.',
            'identityName' => $actor->name,
            'contextLabel' => match ($actor->role) {
                'clinic_owner' => 'Clinic Owner workspace',
                'website_designer' => 'Website Designer workspace',
                'super_admin' => 'Super Admin workspace',
                default => 'SYIFA.my workspace',
            },
        ];
    }

    /** @return list<array<string, mixed>> */
    private function superAdminNavigation(): array
    {
        $items = [
            ['dashboard', 'Dashboard', 'dashboard'],
            ['registrations', 'Registrations', 'dashboard.registrations'],
            ['tenants', 'Tenants', 'dashboard.tenants'],
            ['onboarding-management', 'Onboarding', 'dashboard.onboarding-management'],
            ['billing', 'Billing', 'dashboard.billing'],
            ['commercial', 'Commercial', 'dashboard.commercial'],
            ['payment-providers', 'Payment Providers', 'dashboard.payment-providers'],
            ['blog', 'Blog', 'dashboard.blog'],
            ['notifications', 'Notifications', 'dashboard.notifications'],
            ['audit', 'Audit Activity', 'dashboard.audit'],
            ['reports', 'Reports', 'dashboard.reports'],
        ];

        return array_values(array_map(
            static fn (array $item): array => (new DashboardNavigationItem(
                $item[0],
                $item[1],
                route($item[2]),
                $item[0] === 'blog',
            ))->toArray(),
            $items,
        ));
    }

    /** @return array{index: string, create: string, store: string, update: ?string, transition: ?string} */
    private function urls(?stdClass $job = null, ?string $postId = null): array
    {
        if ($job !== null) {
            $parameters = ['jobId' => (string) $job->id];

            return [
                'index' => route('dashboard.onboarding.blog', $parameters),
                'create' => route('dashboard.onboarding.blog.create', $parameters),
                'store' => route('dashboard.onboarding.blog.store', $parameters),
                'update' => $postId === null ? null : route('dashboard.onboarding.blog.update', [...$parameters, 'postId' => $postId]),
                'transition' => $postId === null ? null : route('dashboard.onboarding.blog.transition', [...$parameters, 'postId' => $postId]),
            ];
        }

        return [
            'index' => route('dashboard.blog'),
            'create' => route('dashboard.blog.create'),
            'store' => route('dashboard.blog.store'),
            'update' => $postId === null ? null : route('dashboard.blog.update', $postId),
            'transition' => $postId === null ? null : route('dashboard.blog.transition', $postId),
        ];
    }

    private function assignedJob(AuthorizationContext $actor, string $jobId): stdClass
    {
        $job = $this->connection->table('onboarding_jobs as job')
            ->join('website_designer_assignments as assignment', function ($join): void {
                $join->on('assignment.onboarding_job_id', '=', 'job.id')->on('assignment.tenant_id', '=', 'job.tenant_id');
            })
            ->join('websites as website', 'website.id', '=', 'job.website_id')
            ->where('job.id', $jobId)
            ->where('assignment.platform_identity_id', $actor->identityId)
            ->where('assignment.assignment_status', 'active')
            ->first(['job.id', 'job.tenant_id', 'job.website_id', 'website.clinic_name']);
        abort_if($job === null, 404);

        return $job;
    }

    private function post(string $postId): stdClass
    {
        $post = $this->connection->table('blog_posts')->where('id', $postId)->first();
        abort_if($post === null, 404);

        return $post;
    }

    private function assertPostBelongsToJob(stdClass $post, stdClass $job): void
    {
        abort_unless(
            hash_equals((string) $job->tenant_id, (string) $post->tenant_id)
            && hash_equals((string) $job->website_id, (string) $post->website_id),
            404,
        );
    }
}
