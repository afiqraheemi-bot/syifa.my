<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SubscriptionPersistenceArchitectureTest extends TestCase
{
    public function test_repository_contract_and_implementation_respect_layer_boundaries(): void
    {
        $contract = new ReflectionClass(SubscriptionRepositoryInterface::class);
        $implementation = new ReflectionClass(PostgresSubscriptionRepository::class);

        self::assertTrue($contract->isInterface());
        self::assertStringContainsString('\\Contracts\\', $contract->getName());
        self::assertStringContainsString('\\Infrastructure\\', $implementation->getName());
        self::assertTrue($implementation->implementsInterface(SubscriptionRepositoryInterface::class));
    }

    public function test_subscription_domain_has_no_orm_shared_kernel_or_cross_context_imports(): void
    {
        $directory = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Domain/Aggregates/Subscription';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString('Illuminate\\', $source);
            self::assertStringNotContainsString('SharedKernel', $source);
            self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/', $source);
        }
    }
}
