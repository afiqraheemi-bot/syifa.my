<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\Onboarding\Persistence;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Infrastructure\Persistence\Repositories\PostgresOnboardingJobRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class OnboardingJobRepositoryContractTest extends TestCase
{
    public function test_postgres_adapter_implements_the_single_job_repository_contract(): void
    {
        self::assertTrue(is_subclass_of(
            PostgresOnboardingJobRepository::class,
            OnboardingJobRepositoryInterface::class,
        ));

        $contract = new ReflectionClass(OnboardingJobRepositoryInterface::class);
        self::assertSame(['find', 'save'], array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $contract->getMethods(),
        ));
    }

    public function test_no_independent_assignment_repository_exists(): void
    {
        $root = dirname(__DIR__, 4).'/app/Modules/Onboarding';

        self::assertFileDoesNotExist(
            $root.'/Domain/Aggregates/OnboardingJob/Repositories/WebsiteDesignerAssignmentRepositoryInterface.php',
        );
        self::assertFileDoesNotExist(
            $root.'/Infrastructure/Persistence/Repositories/WebsiteDesignerAssignmentRepository.php',
        );
    }
}
