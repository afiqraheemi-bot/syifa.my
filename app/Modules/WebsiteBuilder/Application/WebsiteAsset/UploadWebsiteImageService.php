<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteAsset;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Contracts\Assets\WebsiteAssetBinaryStorageInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use DateTimeImmutable;
use finfo;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class UploadWebsiteImageService
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsiteAssetBinaryStorageInterface $storage,
        private WebsiteAuthorization $authorization,
    ) {}

    public function upload(UploadWebsiteImageCommand $command): UploadedWebsiteImage
    {
        $tenantId = new TenantId($command->tenantId);
        $websiteId = new WebsiteId($command->websiteId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);
        $website = $this->websites->findById($tenantId, $websiteId)
            ?? throw new RuntimeException('Website was not found.');

        $size = strlen($command->contents);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidWebsiteValueException('Image must be no larger than 8 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($command->contents);
        $mimeType = is_string($mime) ? AssetMimeType::tryFrom($mime) : null;
        if (! in_array($mimeType, [AssetMimeType::Jpeg, AssetMimeType::Png, AssetMimeType::Webp], true)) {
            throw new InvalidWebsiteValueException('Only JPEG, PNG, and WebP images are supported.');
        }
        $dimensions = @getimagesizefromstring($command->contents);
        if (! is_array($dimensions)) {
            throw new InvalidWebsiteValueException('Uploaded image content is invalid.');
        }
        if ($dimensions[0] > 6000 || $dimensions[1] > 6000 || ($dimensions[0] * $dimensions[1]) > 24_000_000) {
            throw new InvalidWebsiteValueException('Image dimensions are too large.');
        }

        $assetId = new AssetId(Uuid::uuid4()->toString());
        $storageKey = 'website-assets/'.$tenantId->value.'/'.$websiteId->value.'/'.$assetId->value;
        $now = new DateTimeImmutable;
        $asset = WebsiteAsset::register(
            $assetId,
            $tenantId,
            $storageKey,
            $mimeType,
            $size,
            (int) $dimensions[0],
            (int) $dimensions[1],
            hash('sha256', $command->contents),
            $now,
        );

        $this->storage->store($storageKey, $command->contents);
        try {
            $website->registerAsset($asset, $now);
            $website->makeAssetAvailable(
                $assetId,
                new AssetAvailabilityEvidence(mimeVerified: true, executableContentRejected: true),
                $now,
            );
            $this->websites->save($website);
        } catch (\Throwable $exception) {
            $this->storage->delete($storageKey);

            throw $exception;
        }

        return new UploadedWebsiteImage(
            $assetId->value,
            $mimeType->value,
            $size,
            (int) $dimensions[0],
            (int) $dimensions[1],
            $website->version(),
        );
    }
}
