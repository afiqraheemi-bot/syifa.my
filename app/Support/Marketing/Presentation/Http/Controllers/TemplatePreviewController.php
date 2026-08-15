<?php

declare(strict_types=1);

namespace App\Support\Marketing\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\TemplatePreviewRenderModelFactory;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a live preview of one of the five official public Website
 * template personalities (see
 * `docs/public-website/17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`) through the
 * exact same rendering pipeline a real published Website uses
 * (`PublicWebsiteDocumentFactory` + `resources/views/public-website/document.blade.php`
 * sections), filled with realistic sample content owned by
 * `TemplatePreviewRenderModelFactory`.
 *
 * This deliberately does not maintain a separate, hand-authored mockup per
 * template — a prospect comparing this preview against their own published
 * Website is comparing the same renderer, so the two can never drift apart.
 *
 * This lives outside `app/Http/Controllers` because it depends on
 * WebsiteBuilder module services — `app/Http/Controllers` is reserved for
 * module-free operations/root-entry concerns (see
 * `ProductionOperationsFoundationArchitectureTest`).
 */
final readonly class TemplatePreviewController
{
    private const array SLUGS = [
        'syifa-essential' => TemplateId::SyifaEssential,
        'syifa-care' => TemplateId::SyifaCare,
        'syifa-dental' => TemplateId::SyifaDental,
        'syifa-aesthetic' => TemplateId::SyifaAesthetic,
        'syifa-specialist' => TemplateId::SyifaSpecialist,
    ];

    public function __construct(
        private TemplatePreviewRenderModelFactory $renderModels,
        private PublicWebsiteDocumentFactory $documents,
    ) {}

    public function __invoke(Request $request, string $slug): View
    {
        $templateId = self::SLUGS[$slug] ?? null;
        if ($templateId === null) {
            throw new NotFoundHttpException('Template preview was not found.');
        }

        $context = new PublicSiteContext($request->getScheme(), $request->getHost(), '/templates/preview/'.$slug);
        $model = $this->renderModels->make($templateId);
        $document = $this->documents->make($model, $context);

        return view('public-website.template-preview', [
            'document' => $document,
            'homeUrl' => route('root', [], false),
        ]);
    }
}
