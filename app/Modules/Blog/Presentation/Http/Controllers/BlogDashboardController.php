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
        $query = $this->connection->table('blog_posts as post')->join('websites as website', 'website.id', '=', 'post.website_id');
        if ($actor->role === 'clinic_owner') {
            abort_if($actor->tenantId === null, 403);
            $query->where('post.tenant_id', $actor->tenantId);
        } elseif ($actor->role === 'website_designer') {
            $query->join('onboarding_jobs as job', 'job.website_id', '=', 'post.website_id')
                ->join('website_designer_assignments as assignment', 'assignment.onboarding_job_id', '=', 'job.id')
                ->where('assignment.platform_identity_id', $actor->identityId)->where('assignment.assignment_status', 'active');
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
        $tenantId = $actor->tenantId;
        $entitled = $actor->role === 'super_admin' || ($tenantId !== null && $this->authorization->entitled($tenantId));
        if ($actor->role === 'website_designer') {
            $assignedTenants = $this->connection->table('website_designer_assignments')->where('platform_identity_id', $actor->identityId)
                ->where('assignment_status', 'active')->distinct()->pluck('tenant_id');
            $entitled = $assignedTenants->contains(fn (mixed $id): bool => $this->authorization->entitled((string) $id));
        }

        return Inertia::render('Blog/BlogIndex', [
            ...$this->shell($actor, false),
            'posts' => $posts,
            'entitled' => $entitled,
            'filters' => $request->only(['search', 'status', 'category']),
            'summary' => collect($posts->items())->countBy('status'),
            'role' => $actor->role,
        ]);
    }

    public function editor(Request $request, ?string $postId = null): Response
    {
        $actor = $this->actor($request);
        $post = $postId === null ? null : $this->connection->table('blog_posts')->where('id', $postId)->first();
        if ($post !== null) {
            $this->authorization->authorize($actor, (string) $post->tenant_id, (string) $post->website_id);
        }

        $websites = [];
        if ($post === null && $actor->role === 'website_designer') {
            $websites = $this->connection->table('websites as website')
                ->join('onboarding_jobs as job', 'job.website_id', '=', 'website.id')
                ->join('website_designer_assignments as assignment', 'assignment.onboarding_job_id', '=', 'job.id')
                ->where('assignment.platform_identity_id', $actor->identityId)
                ->where('assignment.assignment_status', 'active')
                ->get(['website.id', 'website.tenant_id', 'website.clinic_name', 'job.id as onboarding_job_id'])
                ->map(static fn (object $website): array => [
                    'id' => (string) $website->id,
                    'tenant_id' => (string) $website->tenant_id,
                    'clinic_name' => (string) $website->clinic_name,
                    'upload_url' => route('website-designer.website-assets.store', (string) $website->onboarding_job_id),
                ])
                ->values()
                ->all();
        }

        $mediaUploadUrl = $actor->role === 'clinic_owner'
            ? route('clinic-owner.website-assets.store')
            : $this->designerUploadUrl($actor, $post);

        return Inertia::render('Blog/BlogEditor', [
            ...$this->shell($actor, true),
            'post' => $post,
            'role' => $actor->role,
            'websites' => $websites,
            'mediaUploadUrl' => $mediaUploadUrl,
            'assetUrlTemplate' => route('public-website.assets.show', '__ASSET_ID__'),
        ]);
    }

    public function store(Request $request, BlogPostService $service): RedirectResponse
    {
        $actor = $this->actor($request);
        $website = $actor->role === 'website_designer'
            ? $this->connection->table('websites')->where('id', (string) $request->input('website_id'))->first(['id', 'tenant_id'])
            : $this->connection->table('websites')->where('tenant_id', $actor->tenantId)->first(['id', 'tenant_id']);
        abort_if($website === null, 404);
        $id = $service->create($actor, (string) $website->tenant_id, (string) $website->id, $this->validate($request));

        return redirect()->route('dashboard.blog.edit', $id)->with('success', 'Draf artikel disimpan.');
    }

    public function update(Request $request, string $postId, BlogPostService $service): RedirectResponse
    {
        $data = $this->validate($request);
        $service->update($this->actor($request), $postId, (int) $request->validate(['version' => ['required', 'integer', 'min:1']])['version'], $data);

        return back()->with('success', 'Artikel dikemas kini.');
    }

    public function transition(Request $request, string $postId, BlogPostService $service): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['submit_review', 'correction', 'publish', 'schedule', 'archive'])], 'scheduled_at' => ['nullable', 'date']]);
        $service->transition($this->actor($request), $postId, (int) $data['version'], (string) $data['action'], $data['scheduled_at'] ?? null);

        return back()->with('success', 'Status artikel dikemas kini.');
    }

    /** @return array<string, mixed> */
    private function validate(Request $request): array
    {
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
        $data['open_graph_title'] = trim((string) ($data['open_graph_title'] ?? '')) ?: $data['meta_title'];
        $data['open_graph_description'] = trim((string) ($data['open_graph_description'] ?? '')) ?: Str::limit($excerpt, 200, '');
        $data['robots_directive'] = $data['robots_directive'] ?? 'index,follow';

        return $data;
    }

    private function actor(Request $request): AuthorizationContext
    {
        $actor = $request->attributes->get(AuthorizationContext::class);
        abort_unless($actor instanceof AuthorizationContext, 403);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function shell(AuthorizationContext $actor, bool $editor): array
    {
        $navigation = match ($actor->role) {
            'clinic_owner' => ClinicOwnerDashboardNavigation::items('blog'),
            'website_designer' => WebsiteDesignerDashboardNavigation::items('blog'),
            'super_admin' => $this->superAdminNavigation(),
            default => [],
        };

        $breadcrumbs = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
            ['key' => 'blog', 'label' => 'Blog', 'href' => $editor ? route('dashboard.blog') : null],
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

    private function designerUploadUrl(AuthorizationContext $actor, ?stdClass $post): ?string
    {
        if ($actor->role !== 'website_designer' || $post === null) {
            return null;
        }

        $jobId = $this->connection->table('onboarding_jobs as job')
            ->join('website_designer_assignments as assignment', 'assignment.onboarding_job_id', '=', 'job.id')
            ->where('job.website_id', (string) $post->website_id)
            ->where('assignment.platform_identity_id', $actor->identityId)
            ->where('assignment.assignment_status', 'active')
            ->value('job.id');

        return is_string($jobId) ? route('website-designer.website-assets.store', $jobId) : null;
    }
}
