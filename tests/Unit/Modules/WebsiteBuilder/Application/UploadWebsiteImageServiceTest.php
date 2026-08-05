<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\WebsiteAsset\UploadWebsiteImageCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteAsset\UploadWebsiteImageService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Assets\WebsiteAssetBinaryStorageInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UploadWebsiteImageServiceTest extends TestCase
{
    public function test_assigned_designer_uploads_a_verified_website_owned_image(): void
    {
        $website = $this->website();
        $repository = new UploadImageWebsiteRepository($website);
        $storage = new UploadImageBinaryStorage;
        $result = (new UploadWebsiteImageService(
            $repository,
            $storage,
            new WebsiteAuthorization,
        ))->upload(new UploadWebsiteImageCommand(
            new WebsiteAuthorizationContext($this->uuid(9), 'website_designer', assignedTenantId: $this->uuid(2)),
            $this->uuid(2),
            $this->uuid(1),
            $this->png(),
        ));

        self::assertSame('image/png', $result->mimeType);
        self::assertSame(1, $result->width);
        self::assertSame(1, $result->height);
        self::assertCount(1, $website->assets()->assets());
        self::assertSame($this->uuid(2), $website->assets()->assets()[0]->tenantId->value);
        self::assertArrayHasKey($website->assets()->assets()[0]->storageKey, $storage->files);
        self::assertSame(1, $repository->saveCount);
    }

    public function test_non_image_content_is_rejected_without_storage_or_persistence(): void
    {
        $repository = new UploadImageWebsiteRepository($this->website());
        $storage = new UploadImageBinaryStorage;
        $service = new UploadWebsiteImageService($repository, $storage, new WebsiteAuthorization);

        try {
            $service->upload(new UploadWebsiteImageCommand(
                new WebsiteAuthorizationContext($this->uuid(9), 'website_designer', assignedTenantId: $this->uuid(2)),
                $this->uuid(2),
                $this->uuid(1),
                '<script>alert(1)</script>',
            ));
            self::fail('Expected invalid image rejection.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame([], $storage->files);
            self::assertSame(0, $repository->saveCount);
        }
    }

    private function website(): Website
    {
        return Website::create(
            new WebsiteId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            TemplateId::SyifaEssential,
            new WebsiteBranding('Klinik Syifa', null, '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'),
            array_map(fn (int $number): SectionId => new SectionId($this->uuid($number)), range(100, 108)),
            new DateTimeImmutable('2026-08-04T00:00:00Z'),
        );
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class UploadImageWebsiteRepository implements WebsiteRepositoryInterface
{
    public int $saveCount = 0;

    public function __construct(private readonly Website $website) {}

    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value
            && $websiteId->value === $this->website->id->value ? $this->website : null;
    }

    public function findByTenant(TenantId $tenantId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value ? $this->website : null;
    }

    public function save(Website $website): void
    {
        $this->saveCount++;
        $website->synchronizeVersion($website->version() + 1);
        foreach ($website->assets()->assets() as $asset) {
            if ($asset->version() === 0) {
                $asset->synchronizeVersion(1);
            }
        }
    }
}

final class UploadImageBinaryStorage implements WebsiteAssetBinaryStorageInterface
{
    /** @var array<string, string> */
    public array $files = [];

    public function store(string $storageKey, string $contents): void
    {
        $this->files[$storageKey] = $contents;
    }

    public function delete(string $storageKey): void
    {
        unset($this->files[$storageKey]);
    }
}
