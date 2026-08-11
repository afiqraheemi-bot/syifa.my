<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Commercial;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListBillingOptionsService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use Illuminate\Support\ViewErrorBag;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class SuperAdminCommercialMutationPage
{
    public function __construct(
        private GetPlanService $plan,
        private GetPlanOfferingService $offering,
        private GetBillingOptionService $billingOption,
        private GetCapabilityDefinitionService $capability,
        private ListBillingOptionsService $billingOptions,
        private PricingHistoryReadInterface $pricingHistory,
    ) {}

    public function createPackage(mixed $context): DashboardPageView
    {
        return $this->view($this->authorization($context), 'package-create');
    }

    public function createPlan(mixed $context): DashboardPageView
    {
        return $this->view($this->authorization($context), 'plan-create');
    }

    public function createBillingOption(mixed $context): DashboardPageView
    {
        return $this->view($this->authorization($context), 'billing-option-create');
    }

    public function createCapability(mixed $context): DashboardPageView
    {
        return $this->view($this->authorization($context), 'capability-create');
    }

    public function editCapability(mixed $context, string $capabilityId): DashboardPageView
    {
        $capability = $this->capability->execute($capabilityId)
            ?? throw new NotFoundHttpException('Feature definition was not found.');

        return $this->view(
            $this->authorization($context),
            'capability-edit',
            capability: $capability,
        );
    }

    public function editBillingOption(mixed $context, string $billingOptionId): DashboardPageView
    {
        $billingOption = $this->billingOption->execute($billingOptionId)
            ?? throw new NotFoundHttpException('Billing Option was not found.');

        return $this->view(
            $this->authorization($context),
            'billing-option-edit',
            billingOption: $billingOption,
        );
    }

    public function editPlan(mixed $context, string $planId): DashboardPageView
    {
        return $this->view(
            $this->authorization($context),
            'plan-edit',
            $this->findPlan($planId),
        );
    }

    public function createOffering(mixed $context, string $planId): DashboardPageView
    {
        return $this->view(
            $this->authorization($context),
            'offering-create',
            $this->findPlan($planId),
        );
    }

    public function editOffering(
        mixed $context,
        string $planId,
        string $offeringId,
    ): DashboardPageView {
        $plan = $this->findPlan($planId);
        $offering = $this->offering->execute($offeringId);
        if ($offering === null || $offering->planId !== $planId) {
            throw new NotFoundHttpException('Plan Offering was not found.');
        }

        return $this->view($this->authorization($context), 'offering-edit', $plan, $offering);
    }

    private function view(
        AuthorizationContext $context,
        string $formKind,
        ?PlanData $plan = null,
        ?PlanOfferingData $offering = null,
        ?BillingOptionData $billingOption = null,
        ?CapabilityDefinitionData $capability = null,
    ): DashboardPageView {
        $isPackage = str_starts_with($formKind, 'package-');
        $isPlan = str_starts_with($formKind, 'plan-');
        $isBillingOption = str_starts_with($formKind, 'billing-option-');
        $isCapability = str_starts_with($formKind, 'capability-');
        $isCreate = str_ends_with($formKind, '-create');
        $resourceLabel = $isPackage
            ? 'subscription package'
            : ($isPlan
            ? 'subscription plan'
            : ($isBillingOption ? 'billing cycle' : ($isCapability ? 'plan feature' : 'plan price')));
        $title = $isCreate ? 'Create '.$resourceLabel : 'Edit '.$resourceLabel;
        $cancelUrl = $offering === null
            ? ($plan === null ? route('dashboard.commercial') : route('dashboard.commercial.plans.show', $plan->planId))
            : route('dashboard.commercial.plans.offerings.show', [
                'planId' => $plan?->planId,
                'offeringId' => $offering->planOfferingId,
            ]);

        return new DashboardPageView('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', [
            'navigation' => $this->navigation(),
            'breadcrumbs' => $this->breadcrumbs($title, $plan),
            'pageTitle' => $title,
            'pageDescription' => $this->pageDescription($formKind, $plan),
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'csrfToken' => csrf_token(),
            'formKind' => $formKind,
            'action' => $this->action($formKind, $plan, $offering, $billingOption, $capability),
            'cancelUrl' => $cancelUrl,
            'error' => session('commercial_error'),
            'validationErrors' => $this->validationErrors(),
            'oldInput' => [
                'code' => old('code'),
                'name' => old('name'),
                'description' => old('description'),
                'display_order' => old('display_order'),
                'recurrence_classification' => old('recurrence_classification'),
                'interval_unit' => old('interval_unit'),
                'interval_count' => old('interval_count'),
                'billing_option_id' => old('billing_option_id'),
                'amount_minor' => old('amount_minor'),
                'effective_start' => old('effective_start'),
                'effective_end' => old('effective_end'),
                'capability_configuration_reference' => old('capability_configuration_reference'),
                'capability_key' => old('capability_key'),
                'commercial_meaning' => old('commercial_meaning'),
            ],
            'plan' => $plan === null ? null : [
                'id' => $plan->planId,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'displayOrder' => $plan->displayOrder,
                'version' => $plan->version,
            ],
            'offering' => $offering === null ? null : [
                'id' => $offering->planOfferingId,
                'billingOptionId' => $offering->billingOptionId,
                'amountMinor' => $offering->amountMinor,
                'effectiveStart' => $offering->effectiveStart,
                'effectiveEnd' => $offering->effectiveEnd,
                'featureConfiguration' => $offering->capabilityConfigurationReference,
                'displayOrder' => $offering->displayOrder,
                'version' => $this->pricingHistory->forPlanOffering($offering->planOfferingId)[0]->version ?? 0,
            ],
            'billingOption' => $billingOption === null ? null : [
                'id' => $billingOption->billingOptionId,
                'code' => $billingOption->code,
                'name' => $billingOption->name,
                'availability' => $billingOption->availability,
                'recurrence' => $billingOption->recurrenceClassification,
                'intervalUnit' => $billingOption->intervalUnit,
                'intervalCount' => $billingOption->intervalCount,
                'effectiveStart' => $billingOption->effectiveStart,
                'effectiveEnd' => $billingOption->effectiveEnd,
                'displayOrder' => $billingOption->displayOrder,
                'version' => $billingOption->version,
            ],
            'capability' => $capability === null ? null : [
                'id' => $capability->capabilityId,
                'key' => $capability->capabilityKey,
                'name' => $capability->name,
                'description' => $capability->description,
                'commercialMeaning' => $capability->commercialMeaning,
                'status' => $capability->status,
                'version' => $capability->version,
            ],
            'billingOptions' => array_map(static fn (BillingOptionData $option): array => [
                'id' => $option->billingOptionId,
                'label' => $option->name,
                'availability' => $option->availability,
                'availabilityLabel' => ucwords(str_replace('_', ' ', $option->availability)),
            ], $this->billingOptions->execute(new OffsetPaginationInput(1, 100))->items),
            'billingOptionCreateUrl' => route('dashboard.commercial.billing-options.create'),
        ]);
    }

    private function pageDescription(string $formKind, ?PlanData $plan): string
    {
        return match (true) {
            str_starts_with($formKind, 'package-') => 'Create the plan, billing selection and first MYR price in one guided step.',
            str_starts_with($formKind, 'plan-') => 'Define the plan customers will recognise and select.',
            str_starts_with($formKind, 'billing-option-') => 'Define how often customers are billed. Prices are configured separately.',
            str_starts_with($formKind, 'capability-') => 'Describe one approved customer-facing feature that a plan may include.',
            default => sprintf(
                'Set the price and billing cycle for %s.',
                $plan instanceof PlanData ? $plan->name : 'this plan',
            ),
        };
    }

    private function findPlan(string $planId): PlanData
    {
        return $this->plan->execute($planId)
            ?? throw new NotFoundHttpException('Plan was not found.');
    }

    private function action(
        string $formKind,
        ?PlanData $plan,
        ?PlanOfferingData $offering,
        ?BillingOptionData $billingOption = null,
        ?CapabilityDefinitionData $capability = null,
    ): string {
        return match ($formKind) {
            'package-create' => route('dashboard.commercial.packages.store'),
            'plan-create' => route('dashboard.commercial.plans.store'),
            'billing-option-create' => route('dashboard.commercial.billing-options.store'),
            'billing-option-edit' => route('dashboard.commercial.billing-options.update', $billingOption?->billingOptionId),
            'capability-create' => route('dashboard.commercial.capabilities.store'),
            'capability-edit' => route('dashboard.commercial.capabilities.update', $capability?->capabilityId),
            'plan-edit' => route('dashboard.commercial.plans.update', $plan?->planId),
            'offering-create' => route('dashboard.commercial.offerings.store'),
            'offering-edit' => route('dashboard.commercial.offerings.update', $offering?->planOfferingId),
            default => throw new LogicException('Unsupported Commercial mutation form.'),
        };
    }

    /** @return list<array{key: string, label: string, href?: string}> */
    private function breadcrumbs(string $title, ?PlanData $plan): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
            ['key' => 'commercial', 'label' => 'Commercial', 'href' => route('dashboard.commercial')],
        ];
        if ($plan !== null) {
            $items[] = [
                'key' => 'plan',
                'label' => $plan->name,
                'href' => route('dashboard.commercial.plans.show', $plan->planId),
            ];
        }
        $items[] = ['key' => 'form', 'label' => $title];

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function navigation(): array
    {
        return [
            (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
            (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
            (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
            (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
            (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
            (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), true))->toArray(),
            (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
            (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
        ];
    }

    /** @return list<string> */
    private function validationErrors(): array
    {
        $errors = session('errors');

        return $errors instanceof ViewErrorBag
            ? array_values($errors->getBag('commercial')->all())
            : [];
    }

    private function authorization(mixed $context): AuthorizationContext
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        return $context;
    }
}
