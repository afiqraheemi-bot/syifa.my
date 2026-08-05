<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a static, presentation-only design preview of one of the five
 * governed public Website template personalities (see
 * `docs/public-website/17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`). This is a
 * marketing/design artifact only — it carries no tenant data, no booking
 * logic, and no persistence; it exists so prospects and internal reviewers
 * can preview template quality before a template is wired into the real
 * Website Builder rendering pipeline.
 */
final class TemplatePreviewController
{
    private const array COMPONENTS = [
        'syifa-dental' => 'Shared/Marketing/TemplatePreview/SyifaDental',
        'syifa-care' => 'Shared/Marketing/TemplatePreview/SyifaCare',
        'syifa-specialist' => 'Shared/Marketing/TemplatePreview/SyifaSpecialist',
        'syifa-aesthetic' => 'Shared/Marketing/TemplatePreview/SyifaAesthetic',
    ];

    public function __invoke(string $slug): Response
    {
        $component = self::COMPONENTS[$slug] ?? null;
        if ($component === null) {
            throw new NotFoundHttpException('Template preview was not found.');
        }

        return Inertia::render($component, [
            'homeUrl' => route('root', [], false),
        ]);
    }
}
