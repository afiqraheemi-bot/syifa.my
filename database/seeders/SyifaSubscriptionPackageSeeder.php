<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates the governed SYIFA.my package catalogue used by local acceptance
 * environments. The Application layer remains authoritative and every lookup
 * makes this seeder safe to run repeatedly without duplicate catalogue rows.
 */
final class SyifaSubscriptionPackageSeeder extends Seeder
{
    private const string SUPER_ADMIN_ID = '00000000-0000-4000-8000-100000000001';

    /** @var list<array{key: string, name: string, description: string, commercialMeaning: string}> */
    private const array CAPABILITIES = [
        [
            'key' => 'website.managed',
            'name' => 'Managed Clinic Website',
            'description' => 'Create, review and publish a governed clinic website.',
            'commercialMeaning' => 'Includes the managed SYIFA.my website lifecycle.',
        ],
        [
            'key' => 'website.content.manage',
            'name' => 'Website Content Management',
            'description' => 'Manage approved website pages, sections and clinic content.',
            'commercialMeaning' => 'Includes governed Website content editing.',
        ],
        [
            'key' => 'website.branding.manage',
            'name' => 'Website Branding Management',
            'description' => 'Manage approved clinic logo and branding configuration.',
            'commercialMeaning' => 'Includes governed Website branding controls.',
        ],
        [
            'key' => 'website.search_sharing.manage',
            'name' => 'Search and Sharing Management',
            'description' => 'Manage approved SEO and social sharing metadata.',
            'commercialMeaning' => 'Includes governed search and sharing configuration.',
        ],
        [
            'key' => 'booking.online',
            'name' => 'Online Appointment Booking',
            'description' => 'Allow patients to request appointments through the public clinic website.',
            'commercialMeaning' => 'Includes the public online booking journey.',
        ],
        [
            'key' => 'booking.manage',
            'name' => 'Booking Management',
            'description' => 'Review and manage the clinic booking queue and booking history.',
            'commercialMeaning' => 'Includes Clinic Owner booking operations.',
        ],
        [
            'key' => 'booking.schedule.manage',
            'name' => 'Booking Schedule and Capacity',
            'description' => 'Manage booking hours, slot capacity, closures and calendar exceptions.',
            'commercialMeaning' => 'Includes governed appointment availability controls.',
        ],
        [
            'key' => 'syifa_ai.assist',
            'name' => 'SYIFA AI Assistance',
            'description' => 'Use governed content, SEO quality and Website design assistance.',
            'commercialMeaning' => 'Includes approved SYIFA AI assistance capabilities.',
        ],
        [
            'key' => 'custom_domain',
            'name' => 'Custom Domain',
            'description' => 'Use an approved custom public domain for the clinic Website.',
            'commercialMeaning' => 'Includes the custom-domain add-on capability and Designer-managed setup.',
        ],
        [
            'key' => 'website.blog.manage',
            'name' => 'Clinic Blog Management',
            'description' => 'Membolehkan pengurusan dan penerbitan artikel kesihatan klinik.',
            'commercialMeaning' => 'Blog klinik, halaman artikel dan SEO artikel.',
        ],
    ];

    /** @var list<array{code: string, name: string, intervalUnit: string, intervalCount: int, displayOrder: int}> */
    private const array BILLING_OPTIONS = [
        [
            'code' => 'syifa-trial-3-day',
            'name' => '3-Day Trial',
            'intervalUnit' => 'day',
            'intervalCount' => 3,
            'displayOrder' => 10,
        ],
        [
            'code' => 'syifa-annual',
            'name' => 'Annual Billing',
            'intervalUnit' => 'year',
            'intervalCount' => 1,
            'displayOrder' => 20,
        ],
    ];

    /** @var list<array{code: string, name: string, description: string, billingOptionCode: string, amountMinor: int, capabilityReference: string, displayOrder: int}> */
    private const array PACKAGES = [
        [
            'code' => 'syifa-trial',
            'name' => 'Syifa Trial',
            'description' => 'Percubaan percuma selama 3 hari untuk Website klinik, pengurusan kandungan dan tempahan pesakit. SYIFA AI dan custom domain tidak disertakan.',
            'billingOptionCode' => 'syifa-trial-3-day',
            'amountMinor' => 0,
            'capabilityReference' => 'package:syifa-trial',
            'displayOrder' => 10,
        ],
        [
            'code' => 'syifa-basic',
            'name' => 'Syifa Basic',
            'description' => 'Pakej tahunan Website klinik profesional dengan pengurusan kandungan dan tempahan pesakit. SYIFA AI dan custom domain tidak disertakan.',
            'billingOptionCode' => 'syifa-annual',
            'amountMinor' => 29900,
            'capabilityReference' => 'package:syifa-basic',
            'displayOrder' => 20,
        ],
        [
            'code' => 'syifa-pro',
            'name' => 'Syifa Pro',
            'description' => 'Pakej tahunan lengkap dengan SYIFA AI, custom domain dan Blog klinik mesra SEO — terbitkan artikel kesihatan dengan metadata, halaman artikel dan sitemap yang diurus secara tersusun.',
            'billingOptionCode' => 'syifa-annual',
            'amountMinor' => 39900,
            'capabilityReference' => 'package:syifa-pro',
            'displayOrder' => 30,
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $occurredAt = $now->format('Y-m-d\TH:i:s\Z');
        $effectiveStart = $now->format('Y-m-d');

        $this->ensureCapabilities($occurredAt);

        $billingOptionIds = [];
        foreach (self::BILLING_OPTIONS as $definition) {
            $billingOptionIds[$definition['code']] = $this->ensureBillingOption(
                $definition,
                $effectiveStart,
                $occurredAt,
            );
        }

        foreach (self::PACKAGES as $definition) {
            $planId = $this->ensurePlan($definition, $occurredAt);
            $this->ensureOffering(
                $planId,
                $billingOptionIds[$definition['billingOptionCode']],
                $definition,
                $effectiveStart,
                $occurredAt,
            );
        }
    }

    /** @param array{code: string, name: string, intervalUnit: string, intervalCount: int, displayOrder: int} $definition */
    private function ensureBillingOption(array $definition, string $effectiveStart, string $occurredAt): string
    {
        $repository = app(BillingOptionRepositoryInterface::class);
        $code = new BillingOptionCode($definition['code']);
        $existing = $repository->findByCode($code);

        if ($existing !== null) {
            return $existing->id->value;
        }

        $created = app(CreateBillingOptionService::class)->execute(new CreateBillingOptionCommand(
            $definition['code'],
            $definition['name'],
            'recurring',
            $definition['intervalUnit'],
            $definition['intervalCount'],
            $definition['displayOrder'],
            $effectiveStart,
            null,
            $occurredAt,
            self::SUPER_ADMIN_ID,
            (string) Str::uuid(),
        ));

        return $created->id->value;
    }

    /** @param array{code: string, name: string, description: string, billingOptionCode: string, amountMinor: int, capabilityReference: string, displayOrder: int} $definition */
    private function ensurePlan(array $definition, string $occurredAt): string
    {
        $repository = app(PlanRepositoryInterface::class);
        $existing = $repository->findByCode(new PlanCode($definition['code']));

        if ($existing === null) {
            $existing = app(CreatePlanService::class)->execute(new CreatePlanCommand(
                $definition['code'],
                $definition['name'],
                $definition['description'],
                $definition['displayOrder'],
                $occurredAt,
                self::SUPER_ADMIN_ID,
                (string) Str::uuid(),
            ));
            $existing = $repository->findById($existing->id)
                ?? throw new RuntimeException('The seeded subscription package could not be reloaded.');
        }

        if ($existing->lifecycle->status === PlanStatus::Draft) {
            app(ActivatePlanService::class)->execute(new ActivatePlanCommand(
                $existing->id->value,
                $existing->version(),
                $occurredAt,
                self::SUPER_ADMIN_ID,
                (string) Str::uuid(),
            ));
        } elseif ($existing->lifecycle->status !== PlanStatus::Active) {
            throw new RuntimeException(sprintf(
                'Official package %s already exists with unsupported status %s.',
                $definition['code'],
                $existing->lifecycle->status->value,
            ));
        }

        return $existing->id->value;
    }

    /**
     * @param  array{code: string, name: string, description: string, billingOptionCode: string, amountMinor: int, capabilityReference: string, displayOrder: int}  $definition
     */
    private function ensureOffering(
        string $planId,
        string $billingOptionId,
        array $definition,
        string $effectiveStart,
        string $occurredAt,
    ): void {
        $repository = app(PlanOfferingRepositoryInterface::class);
        $existing = null;

        foreach ($repository->findByPlan(new PlanId($planId)) as $offering) {
            if ($offering->billingOptionId->value === $billingOptionId
                && $offering->capabilityConfigurationReference === $definition['capabilityReference']) {
                // findByPlan exposes immutable pricing history. Always reload
                // the matching identity from the current-state table before a
                // lifecycle mutation so a previous draft version is never
                // submitted as the optimistic concurrency version.
                $existing = $repository->findById($offering->id);
                break;
            }
        }

        if ($existing === null) {
            $existing = app(CreatePlanOfferingService::class)->execute(new CreatePlanOfferingCommand(
                $planId,
                $billingOptionId,
                $definition['amountMinor'],
                'MYR',
                $effectiveStart,
                null,
                $definition['capabilityReference'],
                $definition['displayOrder'],
                $occurredAt,
                self::SUPER_ADMIN_ID,
                (string) Str::uuid(),
            ));
            $existing = $repository->findById($existing->id)
                ?? throw new RuntimeException('The seeded package price could not be reloaded.');
        }

        if ($existing->currentPrice->amountMinor !== $definition['amountMinor']
            || $existing->currentPrice->currencyCode !== 'MYR') {
            throw new RuntimeException(sprintf(
                'Official package %s already exists with a different authoritative price.',
                $definition['code'],
            ));
        }

        if ($existing->status === PlanOfferingStatus::Draft) {
            app(ActivatePlanOfferingService::class)->execute(new ActivatePlanOfferingCommand(
                $existing->id->value,
                $existing->version(),
                $occurredAt,
                self::SUPER_ADMIN_ID,
                (string) Str::uuid(),
            ));
        } elseif ($existing->status !== PlanOfferingStatus::Active) {
            throw new RuntimeException(sprintf(
                'Official package %s price already exists with unsupported status %s.',
                $definition['code'],
                $existing->status->value,
            ));
        }
    }

    private function ensureCapabilities(string $occurredAt): void
    {
        $repository = app(CapabilityDefinitionRepositoryInterface::class);

        foreach (self::CAPABILITIES as $definition) {
            $existing = $repository->findByKey(new CapabilityKey($definition['key']));

            if ($existing === null) {
                $existing = app(CreateCapabilityDefinitionService::class)->execute(
                    new CreateCapabilityDefinitionCommand(
                        $definition['key'],
                        $definition['name'],
                        $definition['description'],
                        $definition['commercialMeaning'],
                        $occurredAt,
                        self::SUPER_ADMIN_ID,
                        (string) Str::uuid(),
                    ),
                );
                $existing = $repository->findById($existing->id)
                    ?? throw new RuntimeException('The seeded capability could not be reloaded.');
            }

            if ($existing->status === CapabilityStatus::Draft) {
                app(ActivateCapabilityDefinitionService::class)->execute(new ActivateCapabilityDefinitionCommand(
                    $existing->id->value,
                    $existing->version(),
                    $occurredAt,
                    self::SUPER_ADMIN_ID,
                    (string) Str::uuid(),
                ));
            } elseif ($existing->status !== CapabilityStatus::Active) {
                throw new RuntimeException(sprintf(
                    'Required capability %s exists with unsupported status %s.',
                    $definition['key'],
                    $existing->status->value,
                ));
            }
        }
    }
}
