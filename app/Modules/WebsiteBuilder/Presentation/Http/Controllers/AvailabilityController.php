<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingDraft;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns a server-rendered Blade partial for a requested local date — not a
 * JSON API (ADR-029) — so the Time chip list can be requested with or without
 * client-side enhancement.
 */
final readonly class AvailabilityController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private BookingDeliveryService $delivery,
    ) {}

    /** Read-only — no GET route ever mutates (ADR-029). The date choice is only persisted by POST /booking/date. */
    public function forDate(Request $request): View
    {
        $context = $this->contexts->forHost($request->getHost());
        if ($context === null || $context->websiteId === null) {
            throw new NotFoundHttpException;
        }

        $date = (string) $request->query('date', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new NotFoundHttpException;
        }

        return view('public-website.booking._availability-times', [
            'timeViewModel' => $this->delivery->timeSelectionViewModel($context, (new BookingDraft)->withDate($date)),
        ]);
    }
}
