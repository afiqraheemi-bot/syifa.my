<?php

declare(strict_types=1);

namespace App\Modules\Blog\Infrastructure;

use stdClass;

final readonly class BlogPostRecord
{
    public function __construct(
        public string $tenant_id,
        public string $website_id,
        public string $status,
        public int $version,
    ) {}

    public static function fromDatabase(stdClass $row): self
    {
        return new self(
            (string) $row->tenant_id,
            (string) $row->website_id,
            (string) $row->status,
            (int) $row->version,
        );
    }
}
