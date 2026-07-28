<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\CustomDomain\ManageCustomDomainService;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\ClinicOwnerCustomDomainPage;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerCustomDomainController
{
    public function index(Request $request, ClinicOwnerCustomDomainPage $page, WebsiteReadInterface $websites): Response
    {
        $context = $this->context($request);
        $website = $websites->detail((string) $context->tenantId);
        $token = $website === null ? '' : $this->token($website->id);
        $view = $page->fromTrustedContext($context, $token);

        return Inertia::render($view->component, $view->props);
    }

    public function store(Request $request, WebsiteReadInterface $websites, ManageCustomDomainService $domains): RedirectResponse
    {
        $validated = $request->validate(['hostname' => ['required', 'string', 'max:253']]);
        $context = $this->context($request);
        $website = $websites->detail((string) $context->tenantId);
        abort_if($website === null, 404);
        $id = (string) Str::uuid();
        try {
            $domains->request((string) $context->tenantId, $website->id, (string) $validated['hostname'], $id, $this->token($id), new DateTimeImmutable);
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custom domain requested. Add the DNS proof before verification.');
    }

    public function verify(Request $request, ManageCustomDomainService $domains): RedirectResponse
    {
        $validated = $request->validate(['domain_id' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1']]);
        $context = $this->context($request);
        try {
            $domains->verify((string) $context->tenantId, (string) $validated['domain_id'], (int) $validated['version'], $this->token((string) $validated['domain_id']), new DateTimeImmutable);
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Domain ownership verified.');
    }

    public function activate(Request $request, ManageCustomDomainService $domains): RedirectResponse
    {
        $validated = $request->validate(['domain_id' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1']]);
        $context = $this->context($request);
        try {
            $domains->activate((string) $context->tenantId, (string) $validated['domain_id'], (int) $validated['version'], new DateTimeImmutable);
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custom domain activated.');
    }

    public function detach(Request $request, ManageCustomDomainService $domains): RedirectResponse
    {
        $validated = $request->validate(['domain_id' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1']]);
        $context = $this->context($request);
        try {
            $domains->detach((string) $context->tenantId, (string) $validated['domain_id'], (int) $validated['version'], new DateTimeImmutable);
        } catch (InvalidWebsiteValueException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custom domain detached safely.');
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext && $context->tenantId !== null, 403);

        return $context;
    }

    private function token(string $domainId): string
    {
        return hash_hmac('sha256', 'custom-domain|'.$domainId, (string) config('app.key'));
    }
}
