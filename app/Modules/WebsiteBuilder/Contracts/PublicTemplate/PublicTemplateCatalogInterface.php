<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicTemplate;

interface PublicTemplateCatalogInterface
{
    /**
     * @return list<PublicTemplateOption>
     */
    public function options(): array;
}
