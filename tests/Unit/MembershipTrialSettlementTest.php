<?php

namespace Tests\Unit;

use App\Models\Users;
use App\Services\Membership\MembershipAttemptStatus;
use App\Services\Membership\MembershipProductCatalog;
use App\Services\Membership\MembershipReconciliationService;
use App\Services\Membership\MembershipTrialReservation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class MembershipTrialSettlementTest extends TestCase
{
    public function test_renewed_and_expired_store_purchases_close_the_original_trial_checkout(): void
    {
        $choice = (new MembershipProductCatalog())->checkoutProduct('standard', 'monthly');
        foreach (['active', 'expired'] as $status) {
            $attempt = (object) ['id' => 12, 'quote' => json_encode([
                'product_id' => $choice['store_product_id'], 'base_plan_id' => $choice['base_plan_id'],
            ])];
            $query = Mockery::mock(Builder::class);
            $query->shouldReceive('where')->andReturnSelf();
            $query->shouldReceive('whereIn')->andReturnSelf();
            $query->shouldReceive('latest')->andReturnSelf();
            $query->shouldReceive('get')->once()->andReturn(collect([$attempt]));
            DB::shouldReceive('table')->with('membership_attempts')->once()->andReturn($query);
            $trials = Mockery::mock(Builder::class);
            $trials->shouldReceive('updateOrInsert')->once()->with(['user_id' => 42], Mockery::on(fn ($data) =>
                $data['status'] === 'consumed' && $data['reservation_expires_at'] === null
                && $data['reserved_at'] === null && $data['transaction_id'] === 'verified-order'));
            DB::shouldReceive('table')->with('membership_trials')->once()->andReturn($trials);
            $transitions = Mockery::mock(MembershipAttemptStatus::class);
            $transitions->shouldReceive('transition')->once()
                ->with(12, 'completed', 'verified-order', 'verified_revenuecat_trial')->andReturnTrue();
            $this->app->instance(MembershipAttemptStatus::class, $transitions);

            $result = (new ReflectionMethod(MembershipReconciliationService::class, 'settleVerifiedTrialCheckout'))
                ->invoke($this->app->make(MembershipReconciliationService::class),
                    (new Users())->forceFill(['id' => 42]),
                    ['status' => $status, 'store_subscription_identifier' => 'verified-order',
                        'starts_at' => now()->subHour()->getTimestampMs(),
                        'current_period_starts_at' => now()->subMinutes(5)->getTimestampMs()], $choice);

            $this->assertSame($attempt, $result);
        }
    }

    public function test_missing_store_transaction_cannot_consume_a_trial(): void
    {
        DB::shouldReceive('table')->never();
        $result = (new ReflectionMethod(MembershipReconciliationService::class, 'settleVerifiedTrialCheckout'))
            ->invoke($this->app->make(MembershipReconciliationService::class),
                (new Users())->forceFill(['id' => 42]), ['status' => 'active'], []);
        $this->assertNull($result);
    }

    public function test_only_expired_reserved_trials_are_released_after_verification(): void
    {
        foreach ([
            ['reserved', now()->subMinute(), true],
            ['reserved', null, true],
            ['reserved', now()->addMinutes(5), false],
            ['consumed', now()->subMinute(), false],
        ] as [$status, $expiry, $release]) {
            $query = Mockery::mock(Builder::class);
            $query->shouldReceive('where')->andReturnSelf();
            $query->shouldReceive('lockForUpdate')->once()->andReturnSelf();
            $query->shouldReceive('first')->once()->andReturn((object) [
                'status' => $status, 'reservation_expires_at' => $expiry,
            ]);
            if ($release) {
                $query->shouldReceive('update')->once()->with(Mockery::on(fn ($data) =>
                    $data['status'] === 'eligible' && $data['reserved_at'] === null
                    && $data['reservation_expires_at'] === null));
            } else {
                $query->shouldReceive('update')->never();
            }
            DB::shouldReceive('table')->with('membership_trials')->andReturn($query)->byDefault();
            $this->app->make(MembershipTrialReservation::class)->releaseExpiredAfterVerification(42);
            $this->addToAssertionCount(1);
        }
    }
}
