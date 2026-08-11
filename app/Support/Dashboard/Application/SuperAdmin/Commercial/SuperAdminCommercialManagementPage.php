<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Commercial;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListBillingOptionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListCapabilityDefinitionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlanOfferingsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlansService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SuperAdminCommercialManagementPage
{
    private const int PAGE_SIZE = 100;

    public function __construct(
        private ListPlansService $plans,
        private GetPlanService $plan,
        private ListPlanOfferingsService $offerings,
        private GetPlanOfferingService $offering,
        private ListBillingOptionsService $billingOptions,
        private ListCapabilityDefinitionsService $capabilities,
        private PricingHistoryReadInterface $pricingHistory,
    ) {}

    public function index(mixed $context): DashboardPageView
    {
        $authorization = $this->authorization($context);

        return $this->view($authorization, null, null);
    }

    public function detail(
        mixed $context,
        string $planId,
        ?string $offeringId = null,
    ): DashboardPageView {
        $authorization = $this->authorization($context);
        $plan = $this->plan->execute($planId);
        if ($plan === null) {
            throw new NotFoundHttpException('Plan was not found.');
        }
        $offering = $offeringId === null ? null : $this->offering->execute($offeringId);
        if ($offeringId !== null && ($offering === null || $offering->planId !== $planId)) {
            throw new NotFoundHttpException('Plan Offering was not found.');
        }

        return $this->view($authorization, $plan, $offering);
    }

    private function view(
        AuthorizationContext $context,
        ?PlanData $selectedPlan,
        ?PlanOfferingData $selectedOffering,
    ): DashboardPageView {
        $pagination = new OffsetPaginationInput(1, self::PAGE_SIZE);
        $plans = $this->plans->execute($pagination)->items;
        $currentPlans = array_values(array_filter(
            $plans,
            static fn (PlanData $plan): bool => $plan->status !== 'retired',
        ));
        $archivedPlans = array_values(array_filter(
            $plans,
            static fn (PlanData $plan): bool => $plan->status === 'retired',
        ));
        $offerings = $this->offerings->execute($pagination)->items;
        $billingOptions = $this->billingOptions->execute($pagination)->items;
        $capabilities = array_values(array_filter(
            $this->capabilities->execute($pagination)->items,
            static fn (CapabilityDefinitionData $capability): bool => $capability->status !== 'retired',
        ));
        $planNames = [];
        foreach ($plans as $plan) {
            $planNames[$plan->planId] = $plan->name;
        }
        $billingOptionLabels = [];
        foreach ($billingOptions as $billingOption) {
            $billingOptionLabels[$billingOption->billingOptionId] = $billingOption->name;
        }
        $visibleOfferings = $selectedPlan === null
            ? $offerings
            : array_values(array_filter(
                $offerings,
                static fn (PlanOfferingData $offering): bool => $offering->planId === $selectedPlan->planId,
            ));
        $history = $selectedOffering === null
            ? []
            : $this->pricingHistory->forPlanOffering($selectedOffering->planOfferingId);
        $selectedVersion = $history[0]->version ?? 0;

        return new DashboardPageView('SubscriptionBilling/Commercial/SuperAdminCommercialManagement', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), true))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => $this->breadcrumbs($selectedPlan),
            'pageTitle' => $selectedPlan === null ? 'Commercial management' : $selectedPlan->name,
            'pageDescription' => 'Build and maintain customer-ready subscription packages without exposing catalogue internals.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'csrfToken' => csrf_token(),
            'feedback' => [
                'success' => $this->successMessage(session('operation')),
                'error' => session('commercial_error'),
            ],
            'plans' => array_map($this->planProjection(...), $currentPlans),
            'archivedPlans' => array_map($this->planProjection(...), $archivedPlans),
            'selectedPlan' => $selectedPlan === null ? null : $this->planProjection($selectedPlan),
            'offerings' => array_map(
                fn (PlanOfferingData $item): array => $this->offeringProjection(
                    $item,
                    planNames: $planNames,
                    billingOptionLabels: $billingOptionLabels,
                ),
                $visibleOfferings,
            ),
            'selectedOffering' => $selectedOffering === null
                ? null
                : $this->offeringProjection(
                    $selectedOffering,
                    $selectedVersion,
                    $planNames,
                    $billingOptionLabels,
                ),
            'billingOptions' => array_map(fn (BillingOptionData $item): array => [
                'id' => $item->billingOptionId,
                'code' => $item->code,
                'label' => $item->name,
                'availability' => $item->availability,
                'availabilityLabel' => self::label($item->availability),
                'recurrence' => $item->recurrenceClassification,
                'recurrenceLabel' => self::label($item->recurrenceClassification),
                'intervalUnit' => $item->intervalUnit,
                'intervalCount' => $item->intervalCount,
                'effectiveStart' => $item->effectiveStart,
                'effectiveEnd' => $item->effectiveEnd,
                'editUrl' => route('dashboard.commercial.billing-options.edit', $item->billingOptionId),
            ], $billingOptions),
            'capabilities' => array_map(fn (CapabilityDefinitionData $item): array => [
                'id' => $item->capabilityId,
                'key' => $item->capabilityKey,
                'name' => $item->name,
                'status' => $item->status,
                'statusLabel' => self::label($item->status),
                'description' => $item->description,
                'commercialMeaning' => $item->commercialMeaning,
                'version' => $item->version,
                'editUrl' => route('dashboard.commercial.capabilities.edit', $item->capabilityId),
                'activateUrl' => route('dashboard.commercial.capabilities.activate', $item->capabilityId),
                'deprecateUrl' => route('dashboard.commercial.capabilities.deprecate', $item->capabilityId),
                'retireUrl' => route('dashboard.commercial.capabilities.retire', $item->capabilityId),
            ], $capabilities),
            'pricingHistory' => $selectedOffering === null ? [] : array_map(
                static fn (PricingHistoryData $item): array => [
                    'version' => $item->version,
                    'amount' => self::money($item->amountMinor, $item->currencyCode),
                    'effectiveStart' => $item->effectiveStart,
                    'effectiveEnd' => $item->effectiveEnd,
                    'featureConfiguration' => $item->capabilityConfigurationReference,
                    'recordedAt' => $item->recordedAt,
                ],
                $history,
            ),
            'actions' => [
                'createPackage' => route('dashboard.commercial.packages.create'),
                'previewPackages' => route('dashboard.commercial.website-preview').'#pricing',
                'createPlan' => route('dashboard.commercial.plans.create'),
                'createBillingOption' => route('dashboard.commercial.billing-options.create'),
                'createCapability' => route('dashboard.commercial.capabilities.create'),
                'editPlan' => $selectedPlan === null
                    ? null
                    : route('dashboard.commercial.plans.edit', $selectedPlan->planId),
                'publishPlan' => $selectedPlan === null
                    ? null
                    : route('dashboard.commercial.plans.publish', $selectedPlan->planId),
                'retirePlan' => $selectedPlan === null
                    ? null
                    : route('dashboard.commercial.plans.retire', $selectedPlan->planId),
                'unavailablePlan' => $selectedPlan === null
                    ? null
                    : route('dashboard.commercial.plans.unavailable', $selectedPlan->planId),
                'grandfatherPlan' => $selectedPlan === null
                    ? null
                    : route('dashboard.commercial.plans.grandfather', $selectedPlan->planId),
                'createOffering' => $selectedPlan === null
                    ? null
                    : route('dashboard.commercial.plans.offerings.create', $selectedPlan->planId),
                'editOffering' => $selectedOffering === null
                    ? null
                    : route('dashboard.commercial.plans.offerings.edit', [
                        'planId' => $selectedOffering->planId,
                        'offeringId' => $selectedOffering->planOfferingId,
                    ]),
                'activate' => $selectedOffering === null
                    ? null
                    : route('dashboard.commercial.offerings.activate', $selectedOffering->planOfferingId),
                'retire' => $selectedOffering === null
                    ? null
                    : route('dashboard.commercial.offerings.retire', $selectedOffering->planOfferingId),
                'unavailable' => $selectedOffering === null
                    ? null
                    : route('dashboard.commercial.offerings.unavailable', $selectedOffering->planOfferingId),
                'grandfather' => $selectedOffering === null
                    ? null
                    : route('dashboard.commercial.offerings.grandfather', $selectedOffering->planOfferingId),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function planProjection(PlanData $plan): array
    {
        return [
            'id' => $plan->planId,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'status' => $plan->status,
            'statusLabel' => self::label($plan->status),
            'displayOrder' => $plan->displayOrder,
            'version' => $plan->version,
            'detailUrl' => route('dashboard.commercial.plans.show', $plan->planId),
            'retireUrl' => route('dashboard.commercial.plans.retire', $plan->planId),
        ];
    }

    /**
     * @param  array<string, string>  $planNames
     * @param  array<string, string>  $billingOptionLabels
     * @return array<string, mixed>
     */
    private function offeringProjection(
        PlanOfferingData $offering,
        int $version = 0,
        array $planNames = [],
        array $billingOptionLabels = [],
    ): array {
        $profiles = config('subscription_packages.capability_profiles', []);
        $publicOrder = config('subscription_packages.public_package_order', []);
        $profileConfigured = is_array($profiles)
            && array_key_exists($offering->capabilityConfigurationReference, $profiles);
        $publiclyListed = is_array($publicOrder)
            && in_array($offering->capabilityConfigurationReference, $publicOrder, true);

        return [
            'id' => $offering->planOfferingId,
            'planId' => $offering->planId,
            'billingOptionId' => $offering->billingOptionId,
            'planName' => $planNames[$offering->planId] ?? 'Subscription plan',
            'billingOptionLabel' => $billingOptionLabels[$offering->billingOptionId] ?? 'Billing cycle',
            'amountMinor' => $offering->amountMinor,
            'amount' => self::money($offering->amountMinor, $offering->currencyCode),
            'currency' => $offering->currencyCode,
            'status' => $offering->status,
            'statusLabel' => self::label($offering->status),
            'effectiveStart' => $offering->effectiveStart,
            'effectiveEnd' => $offering->effectiveEnd,
            'featureConfiguration' => $offering->capabilityConfigurationReference,
            'profileConfigured' => $profileConfigured,
            'publiclyListed' => $publiclyListed,
            'websiteReady' => $profileConfigured && $publiclyListed && $offering->status === 'active',
            'displayOrder' => $offering->displayOrder,
            'version' => $version,
            'detailUrl' => route('dashboard.commercial.plans.offerings.show', [
                'planId' => $offering->planId,
                'offeringId' => $offering->planOfferingId,
            ]),
        ];
    }

    /** @return list<array{key: string, label: string, href?: string}> */
    private function breadcrumbs(?PlanData $plan): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
            ['key' => 'commercial', 'label' => 'Commercial', 'href' => route('dashboard.commercial')],
        ];
        if ($plan !== null) {
            $items[] = ['key' => 'plan', 'label' => $plan->name];
        }

        return $items;
    }

    private function authorization(mixed $context): AuthorizationContext
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        return $context;
    }

    private static function money(int $minor, string $currency): string
    {
        return $currency.' '.number_format($minor / 100, 2, '.', ',');
    }

    private static function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function successMessage(mixed $operation): ?string
    {
        return match ($operation) {
            'package_created' => 'Subscription package and its first price were created successfully.',
            'plan_created' => 'Plan created successfully.',
            'plan_updated' => 'Plan updated successfully.',
            'plan_published' => 'Plan published successfully.',
            'plan_unavailable' => 'Plan made unavailable successfully.',
            'plan_grandfathered' => 'Plan grandfathered successfully.',
            'plan_retired' => 'Package removed from the current list and moved to the archive.',
            'offering_created' => 'Plan price created successfully.',
            'offering_updated' => 'Plan price updated successfully.',
            'offering_activated' => 'Plan price activated successfully.',
            'offering_unavailable' => 'Plan price made unavailable successfully.',
            'offering_grandfathered' => 'Plan price grandfathered successfully.',
            'offering_retired' => 'Plan price retired successfully.',
            'billing_option_created' => 'Billing cycle created successfully.',
            'billing_option_updated' => 'Billing cycle updated successfully.',
            'capability_created' => 'Plan feature created successfully.',
            'capability_updated' => 'Plan feature updated successfully.',
            'capability_activated' => 'Plan feature activated successfully.',
            'capability_deprecated' => 'Plan feature deprecated successfully.',
            'capability_retired' => 'Plan feature retired successfully.',
            default => null,
        };
    }
}
