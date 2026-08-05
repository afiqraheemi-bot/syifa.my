<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Assets;

use App\Modules\WebsiteBuilder\Contracts\Assets\WebsiteAssetBinaryStorageInterface;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;

final readonly class LaravelWebsiteAssetBinaryStorage implements WebsiteAssetBinaryStorageInterface
{
    public function __construct(private FilesystemManager $filesystems) {}

    public function store(string $storageKey, string $contents): void
    {
        if (! $this->filesystems->disk('local')->put($storageKey, $contents)) {
            throw new RuntimeException('Website image could not be stored.');
        }
    }

    public function delete(string $storageKey): void
    {
        $this->filesystems->disk('local')->delete($storageKey);
    }
}
