<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\SyifaAi;

enum SyifaAiCapability: string
{
    case ContentAssistant = 'content_assistant';
    case QualityReview = 'quality_review';
    case DesignerCopilot = 'designer_copilot';
}
