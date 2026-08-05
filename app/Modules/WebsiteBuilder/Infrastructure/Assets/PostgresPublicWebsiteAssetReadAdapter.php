<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Assets;

use App\Modules\WebsiteBuilder\Application\WebsiteAsset\PublicWebsiteAssetFile;
use App\Modules\WebsiteBuilder\Contracts\Assets\PublicWebsiteAssetReadInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;

final readonly class PostgresPublicWebsiteAssetReadAdapter implements PublicWebsiteAssetReadInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private FilesystemManager $filesystems,
    ) {}

    public function available(string $assetId): ?PublicWebsiteAssetFile
    {
        $asset = $this->connection->table('website_assets')
            ->where('id', $assetId)
            ->where('status', 'available')
            ->first(['storage_key', 'mime_type', 'checksum']);
        if ($asset === null) {
            return null;
        }
        $disk = $this->filesystems->disk('local');
        if (! $disk->exists((string) $asset->storage_key)) {
            return null;
        }
        $contents = $disk->get((string) $asset->storage_key);
        if (! is_string($contents) || ! hash_equals((string) $asset->checksum, hash('sha256', $contents))) {
            return null;
        }

        return new PublicWebsiteAssetFile(
            $contents,
            (string) $asset->mime_type,
            (string) $asset->checksum,
        );
    }
}
