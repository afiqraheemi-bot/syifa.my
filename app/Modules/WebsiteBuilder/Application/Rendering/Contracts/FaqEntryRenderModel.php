<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class FaqEntryRenderModel
{
    public function __construct(public string $question, public string $answer) {}
}
