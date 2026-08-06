<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\CustomDomain\ManageCustomDomainService;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardPageView;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerCustomDomainPage;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WebsiteDesignerCustomDomainController
{
    public function index(
        Request $request,
        WebsiteDesignerCustomDomainPage $page,
        string $jobId,
    ): Response {
        $view = $this->page($request, $page, $jobId);

        return Inertia::render($view->component, $view->props);
    }

    public function store(
        Request $request,
        WebsiteDesignerCustomDomainPage $page,
        ManageCustomDomainService $domains,
        string $jobId,
    ): RedirectResponse {
        $validated = $request->validate(['hostname' => ['required', 'string', 'max:253']]);
        $view = $this->page($request, $page, $jobId);
        $domainId = (string) Str::uuid();

        try {
            $domains->request(
                (string) $view->props['job']['tenantId'],
                (string) $view->props['job']['websiteId'],
                (string) $validated['hostname'],
                $domainId,
                $this->token($domainId),
                new DateTimeImmutable,
            );
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custom domain requested. Add the DNS proof before verification.');
    }

    public function verify(
        Request $request,
        WebsiteDesignerCustomDomainPage $page,
        ManageCustomDomainService $domains,
        string $jobId,
    ): RedirectResponse {
        $validated = $this->domainMutation($request);
        $view = $this->page($request, $page, $jobId);

        try {
            $domains->verify(
                (string) $view->props['job']['tenantId'],
                $validated['domain_id'],
                $validated['version'],
                $this->token($validated['domain_id']),
                new DateTimeImmutable,
            );
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Domain ownership verified.');
    }

    public function activate(
        Request $request,
        WebsiteDesignerCustomDomainPage $page,
        ManageCustomDomainService $domains,
        string $jobId,
    ): RedirectResponse {
        $validated = $this->domainMutation($request);
        $view = $this->page($request, $page, $jobId);

        try {
            $domains->activate(
                (string) $view->props['job']['tenantId'],
                $validated['domain_id'],
                $validated['version'],
                new DateTimeImmutable,
            );
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custom domain activated.');
    }

    public function detach(
        Request $request,
        WebsiteDesignerCustomDomainPage $page,
        ManageCustomDomainService $domains,
        string $jobId,
    ): RedirectResponse {
        $validated = $this->domainMutation($request);
        $view = $this->page($request, $page, $jobId);

        try {
            $domains->detach(
                (string) $view->props['job']['tenantId'],
                $validated['domain_id'],
                $validated['version'],
                new DateTimeImmutable,
            );
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custom domain detached safely.');
    }

    private function page(
        Request $request,
        WebsiteDesignerCustomDomainPage $page,
        string $jobId,
    ): DashboardPageView {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);
        $view = $page->fromTrustedContext($context, $jobId);
        abort_if($view === null, 404);

        return $view;
    }

    /** @return array{domain_id: string, version: int} */
    private function domainMutation(Request $request): array
    {
        /** @var array{domain_id: string, version: int} $validated */
        $validated = $request->validate([
            'domain_id' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return $validated;
    }

    private function token(string $domainId): string
    {
        return hash_hmac('sha256', 'custom-domain|'.$domainId, (string) config('app.key'));
    }
}
