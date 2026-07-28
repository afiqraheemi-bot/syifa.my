<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Commercial;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListBillingOptionsService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
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
        private ListBillingOptionsService $billingOptions,
        private PricingHistoryReadInterface $pricingHistory,
    ) {}

    public function createPlan(mixed $context): DashboardPageView
    {
        return $this->view($this->authorization($context), 'plan-create');
    }

    public function createBillingOption(mixed $context): DashboardPageView
    {
        return $this->view($this->authorization($context), 'billing-option-create');
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
    ): DashboardPageView {
        $isPlan = str_starts_with($formKind, 'plan-');
        $isBillingOption = str_starts_with($formKind, 'billing-option-');
        $isCreate = str_ends_with($formKind, '-create');
        $resourceLabel = $isPlan ? 'plan' : ($isBillingOption ? 'billing option' : 'offering');
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
            'pageDescription' => 'Manage governed Commercial catalogue values.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'csrfToken' => csrf_token(),
            'formKind' => $formKind,
            'action' => $this->action($formKind, $plan, $offering),
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
            'billingOptions' => array_map(static fn (BillingOptionData $option): array => [
                'id' => $option->billingOptionId,
                'label' => $option->name,
                'availability' => $option->availability,
            ], $this->billingOptions->execute(new OffsetPaginationInput(1, 100))->items),
        ]);
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
    ): string {
        return match ($formKind) {
            'plan-create' => route('dashboard.commercial.plans.store'),
            'billing-option-create' => route('dashboard.commercial.billing-options.store'),
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
            (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
            (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
            (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), true))->toArray(),
            (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
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
