<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CapabilityDefinitionTest extends TestCase
{
    public function test_capability_is_created_as_draft_with_distinct_commercial_meaning(): void
    {
        $capability = $this->capability();

        self::assertSame(CapabilityStatus::Draft, $capability->status);
        self::assertSame('configured_capability', $capability->key->value);
        self::assertSame('Configured capability', $capability->name);
        self::assertSame('Describes the product feature.', $capability->description);
        self::assertSame('Unlocks one governed commercial feature.', $capability->commercialMeaning);
        self::assertFalse($capability->isAvailableForNewPackaging());
        self::assertFalse($capability->isHistoricallyReferenceable());
    }

    public function test_draft_activates_and_active_capability_may_be_packaged(): void
    {
        $active = $this->capability()->activate();

        self::assertSame(CapabilityStatus::Active, $active->status);
        self::assertTrue($active->isAvailableForNewPackaging());
        self::assertTrue($active->isHistoricallyReferenceable());
    }

    public function test_active_capability_can_be_deprecated_and_remains_historically_referenceable(): void
    {
        $deprecated = $this->capability()->activate()->deprecate();

        self::assertSame(CapabilityStatus::Deprecated, $deprecated->status);
        self::assertFalse($deprecated->isAvailableForNewPackaging());
        self::assertTrue($deprecated->isHistoricallyReferenceable());
    }

    #[DataProvider('retirableStatusProvider')]
    public function test_draft_active_or_deprecated_capability_can_be_retired(CapabilityStatus $status): void
    {
        $capability = match ($status) {
            CapabilityStatus::Draft => $this->capability(),
            CapabilityStatus::Active => $this->capability()->activate(),
            CapabilityStatus::Deprecated => $this->capability()->activate()->deprecate(),
            CapabilityStatus::Retired => self::fail('Retired is not a source transition.'),
        };

        $retired = $capability->retire();
        self::assertSame(CapabilityStatus::Retired, $retired->status);
        self::assertFalse($retired->isAvailableForNewPackaging());
        self::assertTrue($retired->isHistoricallyReferenceable());
    }

    /** @return iterable<string, array{CapabilityStatus}> */
    public static function retirableStatusProvider(): iterable
    {
        yield 'draft' => [CapabilityStatus::Draft];
        yield 'active' => [CapabilityStatus::Active];
        yield 'deprecated' => [CapabilityStatus::Deprecated];
    }

    public function test_stable_id_and_key_survive_every_transition(): void
    {
        $draft = $this->capability();
        $active = $draft->activate();
        $deprecated = $active->deprecate();
        $retired = $deprecated->retire();

        foreach ([$active, $deprecated, $retired] as $transitioned) {
            self::assertSame($draft->id, $transitioned->id);
            self::assertSame($draft->key, $transitioned->key);
        }
    }

    public function test_retired_capability_is_terminal(): void
    {
        $retired = $this->capability()->retire();

        $this->expectException(InvalidCommercialCatalogueValueException::class);
        $retired->activate();
    }

    public function test_empty_commercial_meaning_is_rejected(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->capability('');
    }

    private function capability(string $commercialMeaning = 'Unlocks one governed commercial feature.'): CapabilityDefinition
    {
        return new CapabilityDefinition(
            new CapabilityId('00000000-0000-4000-8000-000000000003'),
            new CapabilityKey('configured_capability'),
            'Configured capability',
            'Describes the product feature.',
            $commercialMeaning,
            CapabilityStatus::Draft,
        );
    }
}
