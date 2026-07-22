<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetStatus;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetUsage;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Domain\WebsiteAssetCollection;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebsiteAssetTest extends TestCase
{
    public function test_registration_owns_validated_metadata_and_pending_status(): void
    {
        $asset = $this->asset();
        self::assertSame($this->uuid(1), $asset->id->value);
        self::assertSame($this->uuid(2), $asset->tenantId->value);
        self::assertSame('tenant/assets/hero.webp', $asset->storageKey);
        self::assertSame(AssetMimeType::Webp, $asset->mimeType);
        self::assertSame(2048, $asset->fileSizeBytes);
        self::assertSame(1200, $asset->width);
        self::assertSame(AssetStatus::Pending, $asset->status());
        self::assertSame(0, $asset->version());
    }

    public function test_lifecycle_is_pending_to_available_to_archived_only(): void
    {
        $asset = $this->asset();
        $asset->markAvailable($this->evidence(), $this->at('+1 hour'));
        self::assertSame(AssetStatus::Available, $asset->status());
        $asset->archive($this->at('+2 hours'));
        $asset->archive($this->at('+3 hours'));
        self::assertSame(AssetStatus::Archived, $asset->status());

        $this->expectException(InvalidWebsiteValueException::class);
        $asset->markAvailable($this->evidence(), $this->at('+4 hours'));
    }

    public function test_svg_is_available_for_logo_use_only(): void
    {
        $svg = $this->asset(mimeType: AssetMimeType::Svg);
        $svg->markAvailable($this->evidence(), $this->at('+1 hour'));
        self::assertTrue($svg->isEligibleFor(AssetUsage::Logo));
        self::assertFalse($svg->isEligibleFor(AssetUsage::Favicon));
        self::assertFalse($svg->isEligibleFor(AssetUsage::ContentImage));
        self::assertFalse($svg->isEligibleFor(AssetUsage::DoctorPhoto));
        self::assertFalse($svg->isEligibleFor(AssetUsage::OpenGraphImage));
        self::assertTrue($this->availableAsset(AssetMimeType::Png)->isEligibleFor(AssetUsage::ContentImage));
    }

    #[DataProvider('invalidMetadataProvider')]
    public function test_invalid_metadata_is_rejected(string $storageKey, int $size, ?int $width, ?int $height, string $checksum): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        WebsiteAsset::register(new AssetId($this->uuid(1)), new TenantId($this->uuid(2)), $storageKey, AssetMimeType::Png, $size, $width, $height, $checksum, $this->at());
    }

    /** @return iterable<string, array{string, int, ?int, ?int, string}> */
    public static function invalidMetadataProvider(): iterable
    {
        yield 'blank key' => ['', 1, null, null, str_repeat('a', 64)];
        yield 'absolute key' => ['/asset.png', 1, null, null, str_repeat('a', 64)];
        yield 'traversal key' => ['../asset.png', 1, null, null, str_repeat('a', 64)];
        yield 'zero size' => ['asset.png', 0, null, null, str_repeat('a', 64)];
        yield 'zero width' => ['asset.png', 1, 0, null, str_repeat('a', 64)];
        yield 'invalid checksum' => ['asset.png', 1, null, null, 'sha256'];
    }

    public function test_collection_enforces_tenant_ownership_unique_identity_and_storage_key(): void
    {
        $collection = new WebsiteAssetCollection;
        $tenant = new TenantId($this->uuid(2));
        $collection->add($this->asset(), $tenant);
        self::assertSame($this->uuid(1), $collection->asset(new AssetId($this->uuid(1)))->id->value);

        try {
            $collection->add($this->asset(id: 3), new TenantId($this->uuid(99)));
            self::fail('Expected Tenant ownership rejection.');
        } catch (InvalidWebsiteValueException) {
            self::assertCount(1, $collection->assets());
        }

        $this->expectException(InvalidWebsiteValueException::class);
        $collection->add($this->asset(id: 4), $tenant);
    }

    public function test_unknown_mime_and_status_values_are_rejected(): void
    {
        try {
            AssetMimeType::fromStored('application/javascript');
            self::fail('Expected unsupported MIME rejection.');
        } catch (InvalidWebsiteValueException) {
            self::assertTrue(true);
        }
        $this->expectException(InvalidWebsiteValueException::class);
        AssetStatus::fromStored('deleted');
    }

    public function test_availability_rejects_unverified_or_executable_content(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        new AssetAvailabilityEvidence(true, false);
    }

    private function asset(int $id = 1, AssetMimeType $mimeType = AssetMimeType::Webp): WebsiteAsset
    {
        return WebsiteAsset::register(new AssetId($this->uuid($id)), new TenantId($this->uuid(2)), 'tenant/assets/hero.webp', $mimeType, 2048, 1200, 800, str_repeat('a', 64), $this->at());
    }

    private function availableAsset(AssetMimeType $mimeType): WebsiteAsset
    {
        $asset = $this->asset(mimeType: $mimeType);
        $asset->markAvailable($this->evidence(), $this->at('+1 hour'));

        return $asset;
    }

    private function evidence(): AssetAvailabilityEvidence
    {
        return new AssetAvailabilityEvidence(true, true);
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-11T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
