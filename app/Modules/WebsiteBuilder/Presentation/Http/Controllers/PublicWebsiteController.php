<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicWebsiteController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private PublicWebsiteRenderModelProviderInterface $websites,
        private PublicWebsiteDocumentFactory $documents,
    ) {}

    public function __invoke(Request $request): View
    {
        $context = $this->contexts->forHost($request->getHost());
        if ($context === null) {
            throw new NotFoundHttpException;
        }
        $website = $this->websites->find($context);
        if ($website === null) {
            throw new NotFoundHttpException;
        }

        return view('public-website.document', ['document' => $this->documents->make($website, $context)]);
    }
}
