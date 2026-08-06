<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DashboardShellPresentationArchitectureTest extends TestCase
{
    public function test_commercial_management_delivery_remains_thin_and_presentation_only(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string) file_get_contents($root.'/app/Support/Dashboard/Application/SuperAdmin/Commercial/SuperAdminCommercialManagementPage.php');
        $controller = (string) file_get_contents($root.'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminCommercialOfferingOperationController.php');
        $planController = (string) file_get_contents($root.'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminCommercialPlanOperationController.php');
        $pageController = (string) file_get_contents($root.'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminCommercialManagementController.php');
        $component = (string) file_get_contents($root.'/resources/js/Modules/SubscriptionBilling/Commercial/SuperAdminCommercialManagement.vue');
        $mutationPage = (string) file_get_contents($root.'/app/Support/Dashboard/Application/SuperAdmin/Commercial/SuperAdminCommercialMutationPage.php');
        $mutationComponent = (string) file_get_contents($root.'/resources/js/Modules/SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm.vue');

        self::assertStringContainsString('PricingHistoryReadInterface', $page);
        self::assertStringContainsString('CreatePlanOfferingService', $controller);
        self::assertStringContainsString('UpdatePlanOfferingService', $controller);
        self::assertStringContainsString('ActivatePlanOfferingService', $controller);
        self::assertStringContainsString('RetirePlanOfferingService', $controller);
        self::assertStringNotContainsString('Inertia::clearHistory()', $pageController);
        self::assertStringNotContainsString('Inertia::encryptHistory()', $pageController);
        self::assertStringNotContainsString('commercial_validation', $page);
        self::assertStringNotContainsString('commercial_validation', $controller);
        self::assertStringNotContainsString('commercial_validation', $planController);
        self::assertStringNotContainsString('commercial_validation', $pageController);
        self::assertStringNotContainsString('commercial_validation', $component);
        self::assertStringNotContainsString('window.history.replaceState(', $component);
        self::assertStringNotContainsString("window.addEventListener('pageshow'", $component);
        self::assertStringNotContainsString('window.location.reload()', $component);
        self::assertStringNotContainsString('visibleValidationErrors', $component);
        self::assertStringContainsString('nextTick(() => confirmationButton.value?.focus())', $component);
        self::assertStringContainsString('fixed inset-0 z-50', $component);
        self::assertStringNotContainsString('validationErrors', $page);
        self::assertStringNotContainsString('oldInput', $page);
        self::assertStringNotContainsString('name="code"', $component);
        self::assertStringNotContainsString('name="amount_minor"', $component);
        self::assertStringContainsString("getBag('commercial')", $mutationPage);
        self::assertStringContainsString('validationErrors', $mutationComponent);
        self::assertSame(4, substr_count($mutationComponent, ':disabled="submitting"'));
        self::assertStringContainsString('Price (RM)', $mutationComponent);
        self::assertStringContainsString('name="amount_minor" :value="amountMinor"', $mutationComponent);

        foreach (['DB::', 'RepositoryInterface', 'ConnectionInterface', 'PaymentProvider', 'Stripe', 'ToyyibPay'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller);
            self::assertStringNotContainsString($forbidden, $component);
        }
        foreach (['AuthorizationService', 'PermissionResolver', 'RoleResolver', 'fetch(', 'axios', 'localStorage'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $component);
        }
    }

    public function test_shared_dashboard_component_catalogue_is_complete(): void
    {
        foreach ([
            'DashboardShell',
            'DashboardSidebar',
            'DashboardTopNavigation',
            'DashboardBreadcrumb',
            'DashboardPageHeader',
            'DashboardEmptyState',
            'DashboardLoadingState',
            'DashboardErrorState',
            'DashboardWelcome',
            'DashboardSummaryCard',
            'DashboardQuickActions',
            'DashboardRecentActivity',
            'WebsiteOverviewCard',
            'WebsiteHealthCard',
            'WebsiteInformationCard',
            'WebsiteQuickActions',
            'BookingSummaryGrid',
            'BookingFilters',
            'BookingTable',
            'BookingPagination',
            'ContentHealthSummary',
            'ContentSectionSummary',
        ] as $component) {
            self::assertFileExists($this->dashboardRoot().'/'.$component.'.vue');
            self::assertStringContainsString(
                "export { default as {$component} }",
                $this->source('index.js'),
            );
        }

        self::assertFileExists($this->dashboardRoot().'/navigation.js');
    }

    public function test_shell_renders_every_shared_composition_region(): void
    {
        $shell = $this->source('DashboardShell.vue');

        foreach ([
            'DashboardSidebar',
            'DashboardTopNavigation',
            'DashboardBreadcrumb',
            'DashboardPageHeader',
            'id="dashboard-content"',
            '<slot />',
            '<slot name="top-actions"',
            '<slot name="page-actions"',
        ] as $renderedContract) {
            self::assertStringContainsString($renderedContract, $shell);
        }

        self::assertStringContainsString('Skip to dashboard content', $shell);
        self::assertStringContainsString('tabindex="-1"', $shell);
    }

    public function test_sidebar_supports_desktop_collapse_and_mobile_drawer_modes(): void
    {
        $sidebar = $this->source('DashboardSidebar.vue');
        $shell = $this->source('DashboardShell.vue');

        foreach ([
            'mobileOpen',
            'translate-x-0',
            '-translate-x-full',
            'lg:translate-x-0',
            'lg:w-20',
            'lg:w-72',
            "emit('close-mobile')",
            "emit('toggle-collapse')",
        ] as $responsiveContract) {
            self::assertStringContainsString($responsiveContract, $sidebar);
        }

        self::assertStringContainsString("collapsed ? 'lg:pl-20' : 'lg:pl-72'", $shell);
        self::assertStringContainsString('@open-navigation="mobileOpen = true"', $shell);
        self::assertStringContainsString('@close-mobile="mobileOpen = false"', $shell);
        self::assertStringContainsString('@keydown.esc', $sidebar);
        self::assertStringContainsString('aria-controls="dashboard-navigation"', $this->source('DashboardTopNavigation.vue'));
        self::assertStringContainsString(':aria-expanded="navigationOpen"', $this->source('DashboardTopNavigation.vue'));
    }

    public function test_shared_states_render_accessible_semantics(): void
    {
        self::assertStringContainsString('role="status"', $this->source('DashboardLoadingState.vue'));
        self::assertStringContainsString('aria-live="polite"', $this->source('DashboardLoadingState.vue'));
        self::assertStringContainsString('role="alert"', $this->source('DashboardErrorState.vue'));
        self::assertStringContainsString('aria-label="Breadcrumb"', $this->source('DashboardBreadcrumb.vue'));
        self::assertStringContainsString('aria-current', $this->source('DashboardBreadcrumb.vue'));
        self::assertStringContainsString('<slot name="action"', $this->source('DashboardEmptyState.vue'));
    }

    public function test_navigation_contract_is_immutable_and_contains_no_role_filtering(): void
    {
        $navigation = $this->source('navigation.js');

        self::assertStringContainsString('Object.freeze', $navigation);
        self::assertStringContainsString('createNavigationItem', $navigation);
        self::assertStringContainsString('createNavigationGroup', $navigation);
        self::assertStringContainsString('createDashboardNavigation', $navigation);
        self::assertStringNotContainsString('super_admin', $navigation);
        self::assertStringNotContainsString('website_designer', $navigation);
        self::assertStringNotContainsString('clinic_owner', $navigation);
        self::assertStringNotContainsString('AuthorizationService', $navigation);

        $overview = $this->source('overview.js');
        self::assertStringContainsString('Object.freeze', $overview);
        self::assertStringContainsString('createDashboardQuickActions', $overview);
        self::assertStringContainsString('createDashboardActivity', $overview);
    }

    public function test_dashboard_components_are_presentation_only(): void
    {
        $source = '';
        foreach (glob($this->dashboardRoot().'/*') ?: [] as $file) {
            if (is_file($file)) {
                $source .= (string) file_get_contents($file);
            }
        }

        foreach ([
            'App\\\\Modules',
            'AuthorizationService',
            'PermissionResolver',
            'RoleResolver',
            'fetch(',
            'axios',
            'DB::',
            'Schema::',
            'Eloquent',
            'localStorage',
            'sessionStorage',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_public_delivery_does_not_import_the_dashboard_shell(): void
    {
        $publicSource = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/public-website.js',
        );

        self::assertStringNotContainsString('Shared/Dashboard', $publicSource);
        self::assertStringNotContainsString('DashboardShell', $publicSource);
    }

    public function test_authenticated_inertia_page_composes_the_single_shared_shell(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/Shared/Dashboard/AuthenticatedDashboard.vue',
        );

        self::assertStringContainsString('DashboardShell', $page);
        self::assertStringContainsString('createDashboardNavigation(props.navigation)', $page);
        self::assertStringNotContainsString('AuthorizationService', $page);
        self::assertStringNotContainsString('super_admin', $page);
        self::assertStringNotContainsString('website_designer', $page);
        self::assertStringNotContainsString('clinic_owner', $page);
    }

    public function test_clinic_owner_overview_only_renders_application_supplied_contracts(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/TenantManagement/Dashboard/ClinicOwnerDashboardOverview.vue',
        );

        foreach ([
            'DashboardShell',
            'DashboardWelcome',
            'DashboardSummaryCard',
            'DashboardQuickActions',
            'DashboardRecentActivity',
            'createDashboardQuickActions(props.quickActions)',
            'createDashboardActivity(props.recentActivity)',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }

        foreach ([
            'AuthorizationService',
            'PermissionResolver',
            'RoleResolver',
            'super_admin',
            'website_designer',
            'clinic_owner',
            'fetch(',
            'axios',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }
    }

    public function test_website_designer_overview_only_renders_immutable_application_contracts(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Dashboard/WebsiteDesignerDashboardOverview.vue',
        );

        foreach ([
            'DashboardShell',
            'DashboardWelcome',
            'DashboardSummaryCard',
            'DashboardQuickActions',
            'DashboardRecentActivity',
            'createDashboardSummaries(props.summaries)',
            'createDashboardQuickActions(props.quickActions)',
            'createDashboardActivity(props.recentAssignments)',
            'Recent assignments',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }

        foreach ([
            'AuthorizationService',
            'PermissionResolver',
            'RoleResolver',
            'website_designer',
            'clinic_owner',
            'fetch(',
            'axios',
            'DB::',
            'Schema::',
            'Repository',
            'OnboardingJobStatus',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }
    }

    public function test_super_admin_overview_only_renders_immutable_application_contracts(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Dashboard/SuperAdminDashboardOverview.vue',
        );
        foreach ([
            'DashboardShell',
            'DashboardSummaryCard',
            'DashboardQuickActions',
            'DashboardRecentActivity',
            'createDashboardSummaries(props.summaries)',
            'Recent platform activity',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach ([
            'AuthorizationService', 'Repository', 'DB::', 'Schema::',
            'fetch(', 'axios', 'super_admin', 'clinic_owner', 'website_designer',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }
    }

    public function test_super_admin_dashboard_application_uses_query_contracts_without_persistence_access(): void
    {
        $sources = '';
        foreach (glob(dirname(__DIR__, 2).'/app/Support/Dashboard/Application/SuperAdmin/*.php') ?: [] as $file) {
            $sources .= (string) file_get_contents($file);
        }

        self::assertStringContainsString('PlatformDashboardReadInterface', $sources);
        self::assertStringContainsString('DashboardSectionProviderInterface', $sources);
        foreach (['Repository', 'Postgres', 'DB::', 'Schema::', '\\Domain\\'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $sources);
        }
    }

    public function test_super_admin_tenant_overview_is_projection_only_and_controller_is_thin(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Tenants/SuperAdminTenantOverview.vue',
        );
        foreach (['DashboardShell', 'DashboardEmptyState', 'tenantOverview.search', 'tenantOverview.statusFilter', 'tenantOverview.pagination'] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach (['AuthorizationService', 'Repository', 'DB::', 'Schema::', 'fetch(', 'axios'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminTenantOverviewController.php',
        );
        self::assertStringContainsString('SuperAdminTenantOverviewPage', $controller);
        self::assertStringNotContainsString('TenantOverviewReadInterface', $controller);
        self::assertStringNotContainsString('AuthorizationService', $controller);
    }

    public function test_super_admin_billing_overview_is_projection_only_and_controller_is_thin(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/SubscriptionBilling/Dashboard/SuperAdminBillingOverview.vue',
        );
        foreach (['DashboardShell', 'DashboardEmptyState', 'billingOverview.search', 'billingOverview.statusFilter', 'billingOverview.pagination'] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach (['AuthorizationService', 'Repository', 'DB::', 'Schema::', 'fetch(', 'axios'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminBillingOverviewController.php',
        );
        self::assertStringContainsString('SuperAdminBillingOverviewPage', $controller);
        self::assertStringNotContainsString('BillingOverviewReadInterface', $controller);
        self::assertStringNotContainsString('AuthorizationService', $controller);
        self::assertStringNotContainsString('DB::', $controller);
    }

    public function test_subscription_detail_is_read_only_projection_and_controller_is_thin(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/SubscriptionBilling/Dashboard/SuperAdminSubscriptionDetail.vue',
        );
        foreach (['DashboardShell', 'Renewal timeline', 'Payment history', 'Auto-renew'] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach (['AuthorizationService', 'Repository', 'DB::', 'fetch(', 'axios', 'PATCH', 'DELETE'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminSubscriptionDetailController.php',
        );
        self::assertStringContainsString('SuperAdminSubscriptionDetailPage', $controller);
        self::assertStringNotContainsString('SubscriptionDetailReadInterface', $controller);
        self::assertStringNotContainsString('AuthorizationService', $controller);

        $operations = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/SuperAdminSubscriptionOperationController.php',
        );
        foreach (['ManualRenewSubscriptionInterface', 'EnableAutoRenewInterface', 'CancelAutoRenewInterface'] as $contract) {
            self::assertStringContainsString($contract, $operations);
        }
        foreach (['PrepareRenewalOfferInterface', 'Repository', 'DB::', 'PaymentProvider'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $operations);
        }
    }

    public function test_website_designer_dashboard_providers_use_query_contracts_without_domain_or_repository_access(): void
    {
        $directory = dirname(__DIR__, 2).'/app/Support/Dashboard/Application/WebsiteDesigner';
        $sources = '';

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $sources .= (string) file_get_contents($file);
        }

        self::assertStringContainsString('WebsiteDesignerDashboardReadInterface', $sources);
        self::assertStringContainsString('DashboardSectionProviderInterface', $sources);
        foreach ([
            '\\Domain\\',
            'RepositoryInterface',
            'Postgres',
            'DB::',
            'Schema::',
            'OnboardingJobStatus',
            'AuthorizationService',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $sources);
        }
    }

    public function test_website_designer_onboarding_queue_is_projection_only_and_controller_is_thin(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerOnboardingQueue.vue',
        );
        foreach ([
            'DashboardShell',
            'DashboardEmptyState',
            'onboardingQueue.search',
            'onboardingQueue.statusFilter',
            'onboardingQueue.pagination',
            'Publish readiness',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach ([
            'AuthorizationService',
            'Repository',
            'OnboardingJobStatus',
            'fetch(',
            'axios',
            'DB::',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerOnboardingQueueController.php',
        );
        self::assertStringContainsString('WebsiteDesignerOnboardingQueuePage', $controller);
        self::assertStringNotContainsString('WebsiteDesignerDashboardReadInterface', $controller);
        self::assertStringNotContainsString('AuthorizationService', $controller);
        self::assertStringNotContainsString('DB::', $controller);
    }

    public function test_website_designer_job_detail_is_projection_only_and_has_no_mutation_logic(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );
        foreach ([
            'DashboardShell',
            'DashboardQuickActions',
            'role="progressbar"',
            'job.progress',
            'job.stages',
            'job.timeline',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach ([
            'AuthorizationService',
            'Repository',
            'OnboardingJobStatus',
            'fetch(',
            'axios',
            'DB::',
            'job.status ===',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }

        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerJobDetailController.php',
        );
        self::assertStringContainsString('WebsiteDesignerJobDetailPage', $controller);
        self::assertStringNotContainsString('WebsiteDesignerDashboardReadInterface', $controller);
        self::assertStringNotContainsString('OnboardingJobRepositoryInterface', $controller);
        self::assertStringNotContainsString('AuthorizationService', $controller);
    }

    public function test_website_overview_uses_shared_shell_and_contains_no_business_logic(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/TenantManagement/Website/ClinicOwnerWebsiteOverview.vue',
        );

        foreach ([
            'DashboardShell',
            'WebsiteOverviewCard',
            'WebsiteHealthCard',
            'WebsiteInformationCard',
            'WebsiteQuickActions',
            'createDashboardQuickActions(props.quickActions)',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }

        foreach ([
            'AuthorizationService',
            'PermissionResolver',
            'RoleResolver',
            'fetch(',
            'axios',
            'DB::',
            'Schema::',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }
    }

    public function test_booking_overview_uses_shared_shell_and_contains_no_business_logic(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/TenantManagement/Booking/ClinicOwnerBookingOverview.vue',
        );

        foreach ([
            'DashboardShell',
            'BookingSummaryGrid',
            'BookingFilters',
            'BookingTable',
            'BookingPagination',
            'createDashboardNavigation(props.navigation)',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }

        foreach ([
            'AuthorizationService',
            'PermissionResolver',
            'RoleResolver',
            'fetch(',
            'axios',
            'DB::',
            'Schema::',
            'BookingRepository',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }

        $table = $this->source('BookingTable.vue');
        self::assertStringContainsString('booking.actions', $table);
        self::assertStringContainsString('role="region"', $table);
        self::assertStringContainsString('aria-label="Bookings table"', $table);
        self::assertStringContainsString('tabindex="0"', $table);
        self::assertStringContainsString('window.confirm(action.confirmation)', $table);
        self::assertStringContainsString('<details v-else', $table);
        self::assertStringContainsString('New appointment date', $table);
        self::assertStringContainsString('New appointment time', $table);
        self::assertStringNotContainsString('booking.status ===', $table);
        self::assertStringNotContainsString('clinic_owner', $table);

        $filters = $this->source('BookingFilters.vue');
        self::assertStringContainsString('Reset', $filters);
        self::assertStringContainsString('focus-visible:outline-emerald-600', $filters);
    }

    public function test_website_content_overview_uses_shared_shell_and_contains_no_business_logic(): void
    {
        $page = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/js/Modules/TenantManagement/Website/ClinicOwnerWebsiteContentOverview.vue',
        );

        foreach ([
            'DashboardShell',
            'ContentHealthSummary',
            ':sections="contentSections"',
            'createDashboardNavigation(props.navigation)',
        ] as $contract) {
            self::assertStringContainsString($contract, $page);
        }
        foreach ([
            'AuthorizationService',
            'PermissionResolver',
            'RoleResolver',
            'fetch(',
            'axios',
            'DB::',
            'Schema::',
            'WebsiteRepository',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $page);
        }
    }

    public function test_booking_operation_controller_delegates_without_domain_or_persistence_access(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Modules/Booking/Presentation/Http/Controllers/ClinicOwnerBookingOperationController.php',
        );
        self::assertStringContainsString('ClinicOwnerBookingOperationsInterface', $controller);
        foreach ([
            'BookingRepositoryInterface',
            'BookingHistoryRepositoryInterface',
            'DB::',
            'Schema::',
            'BookingStatus::',
            'BookingSource::',
            'ConfirmBookingService',
            'CancelBookingService',
            'RescheduleBookingService',
            'AuthorizationService',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller);
        }

        $operations = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Modules/Booking/Application/ClinicOwnerBookingOperations.php',
        );
        foreach ([
            'ConfirmBookingService',
            'CancelBookingService',
            'RescheduleBookingService',
            'CompleteBookingService',
            'BookingOwnerCommand',
            'RescheduleBookingCommand',
        ] as $existingApplicationContract) {
            self::assertStringContainsString($existingApplicationContract, $operations);
        }
    }

    public function test_dashboard_controller_contains_no_authorization_or_navigation_decisions(): void
    {
        foreach ([
            'AuthenticatedDashboardController.php',
            'ClinicOwnerWebsiteOverviewController.php',
            'ClinicOwnerWebsiteContentOverviewController.php',
            'ClinicOwnerBookingOverviewController.php',
        ] as $file) {
            $controller = (string) file_get_contents(
                dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/'.$file,
            );

            self::assertStringNotContainsString('AuthorizationService', $controller);
            self::assertStringNotContainsString('PermissionResolver', $controller);
            self::assertStringNotContainsString('RoleResolver', $controller);
            self::assertStringNotContainsString('->can(', $controller);
            self::assertStringNotContainsString('super_admin', $controller);
            self::assertStringNotContainsString('website_designer', $controller);
            self::assertStringNotContainsString('clinic_owner', $controller);
            self::assertStringNotContainsString('RepositoryInterface', $controller);
            self::assertStringNotContainsString('DB::', $controller);
        }

        self::assertStringContainsString(
            'AuthenticatedDashboardPage',
            (string) file_get_contents(
                dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/AuthenticatedDashboardController.php',
            ),
        );
    }

    public function test_dashboard_route_uses_the_shared_authorization_entry_point(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        self::assertSame(1, substr_count($routes, "Route::get('/dashboard'"));
        self::assertStringContainsString(
            "'authorize.context:authenticated,clinic_owner,website_designer,super_admin'",
            $routes,
        );
        self::assertSame(1, substr_count($routes, "Route::get('/dashboard/website'"));
        self::assertSame(1, substr_count($routes, "Route::get('/dashboard/website/content'"));
        self::assertSame(1, substr_count($routes, "Route::get('/dashboard/bookings'"));
        self::assertStringContainsString(
            "Route::get('/dashboard/bookings', ClinicOwnerBookingOverviewController::class)",
            $routes,
        );
        foreach (['confirm', 'cancel', 'reschedule'] as $operation) {
            self::assertSame(1, substr_count($routes, "->name('{$operation}')"));
        }
        self::assertStringContainsString(
            "'authorize.context:clinic_owner,clinic_owner'",
            $routes,
        );
    }

    private function dashboardRoot(): string
    {
        return dirname(__DIR__, 2).'/resources/js/Shared/Dashboard';
    }

    private function source(string $file): string
    {
        $source = file_get_contents($this->dashboardRoot().'/'.$file);
        self::assertIsString($source);

        return $source;
    }
}
