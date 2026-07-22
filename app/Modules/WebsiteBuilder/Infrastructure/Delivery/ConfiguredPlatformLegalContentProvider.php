<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalDocument;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute;

final readonly class ConfiguredPlatformLegalContentProvider implements PlatformLegalContentProviderInterface
{
    /** @var array<string, array{version: string, title: string, paragraphs: list<string>}> */
    private array $documents;

    /** @param array<mixed> $documents */
    public function __construct(array $documents)
    {
        $validated = [];
        foreach ($documents as $route => $document) {
            if (! is_string($route) || ! is_array($document) || ! is_string($document['version'] ?? null) || ! is_string($document['title'] ?? null) || ! is_array($document['paragraphs'] ?? null) || ! array_all($document['paragraphs'], static fn (mixed $paragraph): bool => is_string($paragraph))) {
                continue;
            }
            $validated[$route] = ['version' => $document['version'], 'title' => $document['title'], 'paragraphs' => array_values($document['paragraphs'])];
        }
        $this->documents = $validated;
    }

    public function find(PublicRoute $route): ?PlatformLegalDocument
    {
        if (! in_array($route, [PublicRoute::Privacy, PublicRoute::Terms], true)) {
            return null;
        }
        $document = $this->documents[$route->value] ?? null;

        return $document === null ? null : new PlatformLegalDocument($route, $document['version'], $document['title'], $document['paragraphs']);
    }
}
