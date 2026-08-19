<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionActivationRetryPolicy;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\Jobs\ActivateSubscriptionJob;
use PHPUnit\Framework\TestCase;

/**
 * ActivateSubscriptionJob's queue-level tries/timeout/backoff and
 * SubscriptionActivationRetryPolicy's database claim lease are two
 * independently hardcoded configurations that must stay in a specific
 * relationship for retries to be safe: the job must finish or be killed
 * before the lease it is holding could expire, and a queue-level retry must
 * never be attempted before that same lease has expired (or it would either
 * falsely appear to still be "in progress" forever, or reclaim a lease that
 * is still legitimately held). SubscriptionActivationArchitectureTest checks
 * these values against fixed numbers; this test checks the relationship
 * itself against the policy's live values, so a change to one side without
 * the other fails here even if both individually still look plausible.
 */
final class ActivateSubscriptionJobRetryConfigurationTest extends TestCase
{
    public function test_job_timeout_stays_shorter_than_the_activation_claim_lease(): void
    {
        $policy = new SubscriptionActivationRetryPolicy;
        $job = new ActivateSubscriptionJob('00000000-0000-4000-8000-000000000001');

        self::assertLessThan($policy->leaseSeconds, $job->timeout, 'A job attempt must be forced to finish or fail before its own database claim lease could expire.');
    }

    public function test_every_backoff_step_starts_after_the_activation_claim_lease_has_expired(): void
    {
        $policy = new SubscriptionActivationRetryPolicy;
        $job = new ActivateSubscriptionJob('00000000-0000-4000-8000-000000000001');

        self::assertNotEmpty($job->backoff);
        foreach ($job->backoff as $step => $delay) {
            self::assertGreaterThan($policy->leaseSeconds, $delay, "backoff[{$step}]={$delay}s must exceed the {$policy->leaseSeconds}s claim lease, or a retry could reclaim a lease that is still legitimately held.");
        }
    }

    public function test_job_tries_match_the_activation_retry_policys_max_attempts(): void
    {
        $policy = new SubscriptionActivationRetryPolicy;
        $job = new ActivateSubscriptionJob('00000000-0000-4000-8000-000000000001');

        self::assertSame($policy->maxAttempts, $job->tries, 'The queue-level retry budget and the database attempt_count ceiling must agree, or one gives up before (or long after) the other.');
    }
}
