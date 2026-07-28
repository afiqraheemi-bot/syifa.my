<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Entitlements;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use JsonException;

final readonly class PostgresSubscriptionEntitlementLookup implements SubscriptionEntitlementLookupInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
    {
        return in_array($capabilityKey, $this->getActiveCapabilityKeys($tenantId, $effectiveDateTime), true);
    }

    public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
    {
        $at = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $effectiveDateTime);
        if ($at === false || $at->format('Y-m-d\TH:i:s\Z') !== $effectiveDateTime) {
            return [];
        }
        $row = $this->connection->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('entitlement_status', 'effective')
            ->whereDate('starts_on', '<=', $at->format('Y-m-d'))
            ->whereDate('ends_on', '>=', $at->format('Y-m-d'))
            ->orderByDesc('starts_on')
            ->first(['entitlement_capabilities']);
        if ($row === null || ! is_string($row->entitlement_capabilities)) {
            return [];
        }
        try {
            $decoded = json_decode($row->entitlement_capabilities, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        if (! is_array($decoded)) {
            return [];
        }
        $keys = [];
        foreach ($decoded as $key) {
            if (is_string($key) && trim($key) === $key && $key !== '') {
                $keys[$key] = true;
            }
        }
        $result = array_keys($keys);
        sort($result);

        return $result;
    }
}
