<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Presentation\Http\BookingSuccessTokenStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reachable only via a valid, same-session, unexpired Success Token
 * (ADR-031) — an invalid, expired, or foreign-session token is
 * indistinguishable from one that never existed, closing the enumeration
 * concern a naive "invalid token" message would otherwise reopen.
 */
final readonly class SuccessController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private BookingDeliveryService $delivery,
        private BookingSuccessTokenStore $successTokens,
    ) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $context = $this->contexts->forHost($request->getHost());
        if ($context === null || $context->websiteId === null) {
            throw new NotFoundHttpException;
        }

        $data = $this->successTokens->retrieve($token);
        if ($data === null) {
            return redirect()->route('public-website.booking.start');
        }

        return view('public-website.booking.success', [
            'viewModel' => $this->delivery->successViewModel($context, $data),
            'theme' => $this->delivery->themeViewModel($context),
        ]);
    }
}
