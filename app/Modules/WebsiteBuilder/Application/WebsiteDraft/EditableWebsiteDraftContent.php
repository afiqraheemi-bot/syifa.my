<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteDraft;

use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;

final readonly class EditableWebsiteDraftContent
{
    public function __construct(private WebsiteDraftContent $draft, private WebsiteDraftSectionCodec $codec) {}

    /** @return array{version: int, sections: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'version' => $this->draft->version,
            'sections' => array_map($this->codec->encode(...), $this->draft->sections),
        ];
    }
}
