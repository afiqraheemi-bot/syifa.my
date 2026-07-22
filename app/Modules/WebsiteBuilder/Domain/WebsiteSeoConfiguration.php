<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;

final class WebsiteSeoConfiguration
{
    public function __construct(
        public readonly WebsiteId $websiteId,
        private string $metaTitle,
        private string $metaDescription,
        private ?string $metaKeywords,
        private ?string $canonicalUrl,
        private RobotsDirective $robotsDirective,
        private string $openGraphTitle,
        private string $openGraphDescription,
        private ?string $openGraphImageReference,
        private bool $indexingEnabled,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version = 0,
    ) {
        $this->validateState();
        if ($version < 0 || $updatedAt < $createdAt) {
            throw new InvalidWebsiteValueException('Website SEO persisted state is invalid.');
        }
    }

    public static function defaults(WebsiteId $websiteId, WebsiteBranding $branding, DateTimeImmutable $at): self
    {
        $title = self::defaultText($branding->clinicName, 60, 'Clinic Website');
        $description = self::defaultText($branding->tagline ?? $branding->clinicName, 160, $title);

        return new self($websiteId, $title, $description, null, null, RobotsDirective::IndexFollow, $title, $description, null, true, $at, $at);
    }

    public function configure(
        string $metaTitle,
        string $metaDescription,
        ?string $metaKeywords,
        ?string $canonicalUrl,
        RobotsDirective $robotsDirective,
        string $openGraphTitle,
        string $openGraphDescription,
        ?string $openGraphImageReference,
        bool $indexingEnabled,
        DateTimeImmutable $at,
    ): void {
        $candidate = new self($this->websiteId, $metaTitle, $metaDescription, $metaKeywords, $canonicalUrl, $robotsDirective, $openGraphTitle, $openGraphDescription, $openGraphImageReference, $indexingEnabled, $this->createdAt, $at, $this->version);
        $this->metaTitle = $candidate->metaTitle;
        $this->metaDescription = $candidate->metaDescription;
        $this->metaKeywords = $candidate->metaKeywords;
        $this->canonicalUrl = $candidate->canonicalUrl;
        $this->robotsDirective = $candidate->robotsDirective;
        $this->openGraphTitle = $candidate->openGraphTitle;
        $this->openGraphDescription = $candidate->openGraphDescription;
        $this->openGraphImageReference = $candidate->openGraphImageReference;
        $this->indexingEnabled = $candidate->indexingEnabled;
        $this->updatedAt = $candidate->updatedAt;
    }

    public function metaTitle(): string
    {
        return $this->metaTitle;
    }

    public function metaDescription(): string
    {
        return $this->metaDescription;
    }

    public function metaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function canonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function robotsDirective(): RobotsDirective
    {
        return $this->robotsDirective;
    }

    public function openGraphTitle(): string
    {
        return $this->openGraphTitle;
    }

    public function openGraphDescription(): string
    {
        return $this->openGraphDescription;
    }

    public function openGraphImageReference(): ?string
    {
        return $this->openGraphImageReference;
    }

    public function indexingEnabled(): bool
    {
        return $this->indexingEnabled;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidWebsiteValueException('Persisted Website SEO version must be positive.');
        }
        $this->version = $version;
    }

    private function validateState(): void
    {
        self::plainText($this->metaTitle, 60, 'Meta title');
        self::plainText($this->metaDescription, 160, 'Meta description');
        self::optionalPlainText($this->metaKeywords, 255, 'Meta keywords');
        self::plainText($this->openGraphTitle, 60, 'Open Graph title');
        self::plainText($this->openGraphDescription, 160, 'Open Graph description');
        if ($this->canonicalUrl !== null) {
            $parts = parse_url($this->canonicalUrl);
            if (filter_var($this->canonicalUrl, FILTER_VALIDATE_URL) === false || ! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
                throw new InvalidWebsiteValueException('Canonical URL must be a credential-free HTTPS URL.');
            }
        }
        if ($this->openGraphImageReference !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->openGraphImageReference) !== 1) {
            throw new InvalidWebsiteValueException('Open Graph image reference is invalid.');
        }
    }

    private static function plainText(string $value, int $maximum, string $field): void
    {
        if (trim($value) === '' || mb_strlen($value) > $maximum || preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            throw new InvalidWebsiteValueException(sprintf('%s is invalid.', $field));
        }
    }

    private static function optionalPlainText(?string $value, int $maximum, string $field): void
    {
        if ($value !== null) {
            self::plainText($value, $maximum, $field);
        }
    }

    private static function defaultText(string $value, int $maximum, string $fallback): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $plain) === 1) {
            $plain = $fallback;
        }

        return mb_substr($plain, 0, $maximum);
    }
}
