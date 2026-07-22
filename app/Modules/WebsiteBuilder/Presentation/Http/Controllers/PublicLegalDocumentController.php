<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicLegalDocumentController
{
    public function __construct(private PlatformLegalContentProviderInterface $legal) {}

    public function privacy(): View
    {
        return $this->document(PublicRoute::Privacy);
    }

    public function terms(): View
    {
        return $this->document(PublicRoute::Terms);
    }

    private function document(PublicRoute $route): View
    {
        $document = $this->legal->find($route);
        if ($document === null) {
            throw new NotFoundHttpException;
        }

        return view('public-website.legal', ['document' => $document]);
    }
}
