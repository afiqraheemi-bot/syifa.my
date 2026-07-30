<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\Exceptions\InvalidPublicDeliveryValueException;

final readonly class PublicSiteContext
{
    public string $basePath;

    public function __construct(
        public string $scheme,
        public string $host,
        string $basePath = '',
        public ?string $websiteId = null,
        public ?string $tenantId = null,
    ) {
        $hostIsValid = preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?::[0-9]{1,5})?$/', $host) === 1;
        if (! in_array($scheme, ['https', 'http'], true) || ! $hostIsValid || str_contains($host, '..') || str_contains($host, '@')) {
            throw new InvalidPublicDeliveryValueException('Public site origin is invalid.');
        }
        if ($scheme === 'http' && ! self::isLocalHost($host)) {
            throw new InvalidPublicDeliveryValueException('Public sites require HTTPS outside local development.');
        }
        $basePath = $basePath === '' ? '' : '/'.trim($basePath, '/');
        if (str_contains($basePath, '..') || preg_match('/^[a-z0-9\/_-]*$/', $basePath) !== 1) {
            throw new InvalidPublicDeliveryValueException('Public site base path is invalid.');
        }
        if ($websiteId !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $websiteId) !== 1) {
            throw new InvalidPublicDeliveryValueException('Public site Website identity is invalid.');
        }
        if ($tenantId !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $tenantId) !== 1) {
            throw new InvalidPublicDeliveryValueException('Public site Tenant identity is invalid.');
        }
        $this->basePath = $basePath;
    }

    public function origin(): string
    {
        return $this->scheme.'://'.$this->host;
    }

    public function url(string $path = ''): PublicUrl
    {
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, '://')) {
            throw new InvalidPublicDeliveryValueException('Public route path is invalid.');
        }
        $path = $path === '' ? '/' : '/'.ltrim($path, '/');

        return new PublicUrl($this->origin().$this->basePath.$path);
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array(explode(':', $host, 2)[0], ['localhost', '127.0.0.1'], true);
    }
}
