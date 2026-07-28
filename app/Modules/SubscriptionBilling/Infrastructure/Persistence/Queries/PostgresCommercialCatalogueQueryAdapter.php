<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedBillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryReadInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresCommercialCatalogueQueryAdapter implements BillingOptionCatalogueQueryInterface, CapabilityDefinitionCatalogueQueryInterface, CommercialCatalogueQueryInterface, PlanCatalogueQueryInterface, PlanOfferingCatalogueQueryInterface, PricingHistoryReadInterface
{
    private const string PLAN_TABLE = 'commercial_catalogue_plans';

    private const string BILLING_OPTION_TABLE = 'commercial_catalogue_billing_options';

    private const string CAPABILITY_TABLE = 'commercial_catalogue_capabilities';

    private const string PLAN_OFFERING_TABLE = 'commercial_catalogue_plan_offerings';

    public function __construct(private ConnectionInterface $connection) {}

    public function findPlan(string $planId): ?PlanData
    {
        $row = $this->connection->table(self::PLAN_TABLE)->where('id', $planId)->first();

        return $row === null ? null : $this->planData($row);
    }

    public function findBillingOption(string $billingOptionId): ?BillingOptionData
    {
        $row = $this->connection->table(self::BILLING_OPTION_TABLE)->where('id', $billingOptionId)->first();

        return $row === null ? null : $this->billingOptionData($row);
    }

    public function findPlanOffering(string $planOfferingId): ?PlanOfferingData
    {
        $row = $this->connection->table(self::PLAN_OFFERING_TABLE)->where('id', $planOfferingId)->first();

        return $row === null ? null : $this->planOfferingData($row);
    }

    public function findCapability(string $capabilityId): ?CapabilityDefinitionData
    {
        $row = $this->connection->table(self::CAPABILITY_TABLE)->where('id', $capabilityId)->first();

        return $row === null ? null : $this->capabilityData($row);
    }

    public function listPlans(OffsetPaginationInput $pagination): PaginatedPlanData
    {
        return new PaginatedPlanData(
            $this->paginate(
                self::PLAN_TABLE,
                ['display_order', 'code', 'id'],
                $pagination,
                fn (stdClass $row): PlanData => $this->planData($row),
            ),
            $this->meta(self::PLAN_TABLE, $pagination),
        );
    }

    public function listBillingOptions(OffsetPaginationInput $pagination): PaginatedBillingOptionData
    {
        return new PaginatedBillingOptionData(
            $this->paginate(
                self::BILLING_OPTION_TABLE,
                ['display_order', 'code', 'id'],
                $pagination,
                fn (stdClass $row): BillingOptionData => $this->billingOptionData($row),
            ),
            $this->meta(self::BILLING_OPTION_TABLE, $pagination),
        );
    }

    public function listCapabilityDefinitions(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
    {
        return new PaginatedCapabilityDefinitionData(
            $this->paginate(
                self::CAPABILITY_TABLE,
                ['key', 'id'],
                $pagination,
                fn (stdClass $row): CapabilityDefinitionData => $this->capabilityData($row),
            ),
            $this->meta(self::CAPABILITY_TABLE, $pagination),
        );
    }

    public function listPlanOfferings(OffsetPaginationInput $pagination): PaginatedPlanOfferingData
    {
        return new PaginatedPlanOfferingData(
            $this->paginate(
                self::PLAN_OFFERING_TABLE,
                ['effective_start', 'display_order', 'id'],
                $pagination,
                fn (stdClass $row): PlanOfferingData => $this->planOfferingData($row),
            ),
            $this->meta(self::PLAN_OFFERING_TABLE, $pagination),
        );
    }

    public function forPlanOffering(string $planOfferingId): array
    {
        return array_values($this->connection->table('commercial_catalogue_plan_offering_versions')
            ->where('id', $planOfferingId)
            ->orderByDesc('version')
            ->get()
            ->map(fn (stdClass $row): PricingHistoryData => new PricingHistoryData(
                $this->integer($row, 'version'),
                $this->integer($row, 'amount_minor'),
                $this->string($row, 'currency_code'),
                $this->dateString($this->value($row, 'effective_start')),
                $this->nullableDateString($this->value($row, 'effective_end')),
                $this->string($row, 'capability_configuration_reference'),
                $this->dateTimeString($this->value($row, 'created_at')),
            ))
            ->all());
    }

    /**
     * @template T of object
     *
     * @param  list<string>  $orderBy
     * @param  callable(stdClass): T  $map
     * @return list<T>
     */
    private function paginate(string $table, array $orderBy, OffsetPaginationInput $pagination, callable $map): array
    {
        $query = $this->connection->table($table);

        foreach ($orderBy as $column) {
            $query->orderBy($column);
        }

        $meta = $this->meta($table, $pagination);
        /** @var list<stdClass> $rows */
        $rows = $query
            ->offset(($meta->currentPage - 1) * $meta->perPage)
            ->limit($meta->perPage)
            ->get()
            ->all();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $map($row);
        }

        return $items;
    }

    private function meta(string $table, OffsetPaginationInput $pagination): OffsetPaginationMeta
    {
        $total = (int) $this->connection->table($table)->count();
        $lastPage = max(1, (int) ceil(max($total, 1) / $pagination->perPage));
        $currentPage = min($pagination->page, $lastPage);

        if ($total === 0) {
            return new OffsetPaginationMeta($currentPage, $pagination->perPage, 0, 1, null, null);
        }

        $from = (($currentPage - 1) * $pagination->perPage) + 1;
        $to = min($from + $pagination->perPage - 1, $total);

        return new OffsetPaginationMeta($currentPage, $pagination->perPage, $total, $lastPage, $from, $to);
    }

    private function planData(stdClass $row): PlanData
    {
        return new PlanData(
            $this->string($row, 'id'),
            $this->string($row, 'code'),
            $this->string($row, 'name'),
            $this->string($row, 'description'),
            $this->string($row, 'status'),
            $this->integer($row, 'display_order'),
            $this->dateTimeString($this->value($row, 'domain_created_at')),
            $this->dateTimeString($this->value($row, 'domain_last_changed_at')),
            $this->integer($row, 'version'),
        );
    }

    private function billingOptionData(stdClass $row): BillingOptionData
    {
        return new BillingOptionData(
            $this->string($row, 'id'),
            $this->string($row, 'code'),
            $this->string($row, 'name'),
            $this->string($row, 'availability'),
            $this->string($row, 'recurrence_classification'),
            $this->nullableString($row, 'interval_unit'),
            $this->nullableInteger($row, 'interval_count'),
            $this->dateString($this->value($row, 'effective_start')),
            $this->nullableDateString($this->value($row, 'effective_end')),
            $this->integer($row, 'display_order'),
            $this->integer($row, 'version'),
        );
    }

    private function capabilityData(stdClass $row): CapabilityDefinitionData
    {
        return new CapabilityDefinitionData(
            $this->string($row, 'id'),
            $this->string($row, 'key'),
            $this->string($row, 'name'),
            $this->string($row, 'description'),
            $this->string($row, 'commercial_meaning'),
            $this->string($row, 'status'),
            $this->integer($row, 'version'),
        );
    }

    private function planOfferingData(stdClass $row): PlanOfferingData
    {
        return new PlanOfferingData(
            $this->string($row, 'id'),
            $this->string($row, 'plan_id'),
            $this->string($row, 'billing_option_id'),
            $this->integer($row, 'amount_minor'),
            $this->string($row, 'currency_code'),
            $this->string($row, 'status'),
            $this->dateString($this->value($row, 'effective_start')),
            $this->nullableDateString($this->value($row, 'effective_end')),
            $this->string($row, 'configuration_version'),
            $this->string($row, 'capability_configuration_reference'),
            $this->integer($row, 'display_order'),
        );
    }

    private function value(stdClass $row, string $field): mixed
    {
        return $row->{$field} ?? null;
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $this->value($row, $field);

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $this->value($row, $field);

        return is_string($value) ? $value : null;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $this->value($row, $field);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function nullableInteger(stdClass $row, string $field): ?int
    {
        $value = $this->value($row, $field);

        if ($value === null) {
            return null;
        }

        return $this->integer($row, $field);
    }

    private function dateTimeString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->format('Y-m-d\TH:i:s\Z');
        }

        return is_string($value) ? (new DateTimeImmutable($value))->format('Y-m-d\TH:i:s\Z') : '';
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->format('Y-m-d');
        }

        return is_string($value) ? (new DateTimeImmutable($value))->format('Y-m-d') : '';
    }

    private function nullableDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->dateString($value);
    }
}
