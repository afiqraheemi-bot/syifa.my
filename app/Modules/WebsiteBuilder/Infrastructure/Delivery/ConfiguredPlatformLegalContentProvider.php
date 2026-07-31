<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalDocument;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute;
use JsonException;

final readonly class ConfiguredPlatformLegalContentProvider implements PlatformLegalContentProviderInterface
{
    /** @var array<string, array{version: string, title: string, paragraphs: list<string>}> */
    private array $documents;

    /** @param array<mixed> $documents */
    public function __construct(array $documents)
    {
        $validated = [];
        foreach ($documents as $route => $document) {
            if (! is_string($route) || ! is_array($document)) {
                continue;
            }
            $normalized = $this->normalize($document);
            if ($normalized !== null) {
                $validated[$route] = $normalized;
            }
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

    /**
     * @param  array<mixed>  $document
     * @return null|array{version: string, title: string, paragraphs: list<string>}
     */
    private function normalize(array $document): ?array
    {
        $path = $document['path'] ?? null;
        if (is_string($path) && trim($path) !== '') {
            $document = $this->fromFile($path) ?? [];
        }
        $version = $document['version'] ?? null;
        $title = $document['title'] ?? null;
        $paragraphs = $document['paragraphs'] ?? null;
        if (! is_string($version)
            || trim($version) === ''
            || ! is_string($title)
            || trim($title) === ''
            || ! is_array($paragraphs)
            || $paragraphs === []
            || ! array_all($paragraphs, static fn (mixed $paragraph): bool => is_string($paragraph) && trim($paragraph) !== '')) {
            return null;
        }

        return [
            'version' => trim($version),
            'title' => trim($title),
            'paragraphs' => array_values(array_map('trim', $paragraphs)),
        ];
    }

    /** @return null|array<mixed> */
    private function fromFile(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return null;
        }

        try {
            $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($document) ? $document : null;
    }
}
