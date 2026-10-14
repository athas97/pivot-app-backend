<?php

namespace Tests\Unit;

use App\Services\Membership\MembershipProductCatalog;
use PHPUnit\Framework\TestCase;

class MembershipProductCatalogTest extends TestCase
{
    public function test_it_resolves_recurring_products_only_with_an_exact_plan(): void
    {
        $catalog = new MembershipProductCatalog();

        $this->assertSame([
            'package' => 'standard',
            'path' => 'monthly',
            'product_id' => 'standard_android:monthly',
            'store_product_id' => 'standard_android',
            'base_plan_id' => 'monthly',
        ], $catalog->resolve(['standard_android'], 'monthly', 'standard'));

        $this->assertSame('annual', $catalog->resolve(['tier_2_annual:tier-2-annual'], null, 'standard')['path']);
        $this->assertNull($catalog->resolve(['standard_android'], null, 'standard'));
        $this->assertNull($catalog->resolve(['standard_android'], 'annual', 'premium'));
        $this->assertNull($catalog->resolve(['unconfigured_product'], 'monthly', 'standard'));
    }

    public function test_it_resolves_lifetime_products_without_a_recurring_plan(): void
    {
        $choice = (new MembershipProductCatalog())->resolve(['premium_lifetime_android'], null, 'premium');

        $this->assertSame('lifetime', $choice['path']);
        $this->assertNull($choice['base_plan_id']);
    }
}
