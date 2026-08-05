<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Assets;

interface WebsiteAssetBinaryStorageInterface
{
    public function store(string $storageKey, string $contents): void;

    public function delete(string $storageKey): void;
}
