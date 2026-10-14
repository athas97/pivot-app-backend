<?php

namespace Tests\Unit;

use App\Services\RevenueCatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RevenueCatMembershipFetchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.revenuecat.api_key' => 'test-api-key',
            'services.revenuecat.project_id' => 'test-project',
            'services.revenuecat.api_url' => 'https://api.revenuecat.test/v2',
        ]);
    }

    public function test_api_failure_is_not_reported_as_an_empty_purchase_collection(): void
    {
        Log::shouldReceive('warning')->once();
        Http::fake(fn () => Http::response(['error' => 'rate limited'], 429));

        $state = (new RevenueCatService())->fetchCustomerMembershipState('user-1', 'production');

        $this->assertFalse($state['ok']);
        $this->assertSame([], $state['subscriptions']);
        Http::assertSentCount(1);
    }

    public function test_complete_empty_collections_are_distinct_from_api_failure(): void
    {
        Http::fake(fn () => Http::response(['items' => [], 'next_page' => null]));

        $state = (new RevenueCatService())->fetchCustomerMembershipState('user-1', 'production');

        $this->assertTrue($state['ok']);
        $this->assertSame([], $state['subscriptions']);
        $this->assertSame([], $state['purchases']);
        Http::assertSentCount(2);
    }

    public function test_all_pages_are_collected_before_a_state_is_returned(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/subscriptions')) {
                return str_contains($url, 'starting_after=cursor-1')
                    ? Http::response(['items' => [['id' => 'sub-2']], 'next_page' => null])
                    : Http::response(['items' => [['id' => 'sub-1']], 'next_page' => 'https://api.revenuecat.test/v2?page=2&starting_after=cursor-1']);
            }

            return Http::response(['items' => [['id' => 'purchase-1']], 'next_page' => null]);
        });

        $state = (new RevenueCatService())->fetchCustomerMembershipState('user-1', 'production');

        $this->assertTrue($state['ok']);
        $this->assertCount(2, $state['subscriptions']);
        $this->assertCount(1, $state['purchases']);
        Http::assertSentCount(3);
    }
}
