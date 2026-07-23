<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSectionSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSnapshotData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebsitePublishedSnapshotReadAdapter implements WebsitePublishedSnapshotReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function latest(string $websiteId): ?PublishedWebsiteSnapshotData
    {
        $row = $this->connection->table('website_published_snapshots')->where('website_id', $websiteId)->orderByDesc('published_version')->first();
        if ($row === null) {
            return null;
        }
        $publishedAt = $row->published_at;

        return new PublishedWebsiteSnapshotData(
            (string) $row->publication_id, (string) $row->website_id, (int) $row->published_version,
            $publishedAt instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($publishedAt) : new DateTimeImmutable((string) $publishedAt),
            (string) $row->template_id, (string) $row->clinic_name, (string) $row->meta_title, (string) $row->content_fingerprint,
            $this->sections((string) $row->publication_id),
        );
    }

    /** @return list<PublishedWebsiteSectionSummaryData> */
    private function sections(string $publicationId): array
    {
        $content = $this->connection->table('website_published_section_contents')
            ->where('publication_id', $publicationId)
            ->get(['section_id', 'renderable'])
            ->keyBy('section_id');

        return array_values(array_map(function (object $section) use ($publicationId, $content): PublishedWebsiteSectionSummaryData {
            $type = (string) $section->section_type;
            $contentRow = $content->get((string) $section->section_id);
            $renderable = $contentRow !== null && (bool) $contentRow->renderable;
            [$itemCount, $highlights] = $this->evidence(
                $publicationId,
                (string) $section->section_id,
                $type,
                $renderable,
            );

            return new PublishedWebsiteSectionSummaryData(
                $type,
                (int) $section->display_order,
                (bool) $section->enabled,
                $renderable,
                $itemCount,
                $highlights,
            );
        }, $this->connection->table('website_published_snapshot_sections')
            ->where('publication_id', $publicationId)
            ->orderBy('display_order')
            ->get()
            ->all()));
    }

    /** @return array{int, list<string>} */
    private function evidence(string $publicationId, string $sectionId, string $type, bool $renderable): array
    {
        $identity = ['publication_id' => $publicationId, 'section_id' => $sectionId];

        return match ($type) {
            'HERO' => $this->singletonEvidence('website_published_hero_contents', $identity, ['headline']),
            'ABOUT' => $this->singletonEvidence('website_published_about_contents', $identity, ['heading']),
            'SERVICES' => $this->orderedEvidence('website_published_service_items', $identity, 'display_name'),
            'DOCTORS' => $this->orderedEvidence('website_published_doctor_profiles', $identity, 'name', ['visible' => true]),
            'GALLERY' => $this->orderedEvidence('website_published_gallery_images', $identity, ['caption', 'alt_text']),
            'TESTIMONIALS' => $this->orderedEvidence('website_published_testimonials', $identity, 'author_name', ['featured' => true]),
            'FAQ' => $this->orderedEvidence('website_published_faq_entries', $identity, 'question'),
            'CONTACT' => $this->singletonEvidence('website_published_contact_projections', $identity, ['address', 'contact_phone']),
            default => [$renderable ? 1 : 0, []],
        };
    }

    /**
     * @param  array{publication_id: string, section_id: string}  $identity
     * @param  list<string>  $columns
     * @return array{int, list<string>}
     */
    private function singletonEvidence(string $table, array $identity, array $columns): array
    {
        $row = $this->connection->table($table)->where($identity)->first($columns);
        if ($row === null) {
            return [0, []];
        }

        return [1, $this->strings($row, $columns)];
    }

    /**
     * @param  array{publication_id: string, section_id: string}  $identity
     * @param  string|list<string>  $columns
     * @param  array<string, bool>  $filters
     * @return array{int, list<string>}
     */
    private function orderedEvidence(string $table, array $identity, string|array $columns, array $filters = []): array
    {
        $columns = is_string($columns) ? [$columns] : $columns;
        $query = $this->connection->table($table)->where($identity);
        foreach ($filters as $filter => $value) {
            $query->where($filter, $value);
        }
        $rows = $query->orderBy('display_order')->get($columns);
        $highlights = [];
        foreach ($rows as $row) {
            $values = $this->strings($row, $columns);
            if ($values !== []) {
                $highlights[] = $values[0];
            }
        }

        return [$rows->count(), $highlights];
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function strings(object $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $value = $row->{$column} ?? null;
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        return $values;
    }
}
