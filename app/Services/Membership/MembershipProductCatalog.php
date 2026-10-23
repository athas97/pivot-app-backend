<?php

namespace App\Services\Membership;

class MembershipProductCatalog
{
    private const PRODUCTS = [
        'standard_android:monthly' => ['standard', 'monthly', 'standard_android'],
        'standard_android:annual' => ['standard', 'annual', 'standard_android'],
        'premium_android:monthly' => ['premium', 'monthly', 'premium_android'],
        'premium_android:annual' => ['premium', 'annual', 'premium_android'],
        'standard_lifetime_android' => ['standard', 'lifetime', 'standard_lifetime_android'],
        'premium_lifetime_android' => ['premium', 'lifetime', 'premium_lifetime_android'],
        'tier_2:tier2' => ['standard', 'monthly', 'tier_2'],
        'tier_2:monthly' => ['standard', 'monthly', 'tier_2'],
        'tier_2_ios:monthly' => ['standard', 'monthly', 'tier_2_ios'],
        'tier_2_ios:annual' => ['standard', 'annual', 'tier_2_ios'],
        'tier_2_annual' => ['standard', 'annual', 'tier_2_annual'],
        'tier_2_annual:tier-2-annual' => ['standard', 'annual', 'tier_2_annual'],
        'tier_2:tier-2-annual' => ['standard', 'annual', 'tier_2'],
        'tier_2_lifetime' => ['standard', 'lifetime', 'tier_2_lifetime'],
        'tier_3:tier3' => ['premium', 'monthly', 'tier_3'],
        'tier_3:monthly' => ['premium', 'monthly', 'tier_3'],
        'tier_3_ios:monthly' => ['premium', 'monthly', 'tier_3_ios'],
        'tier_3_ios:annual' => ['premium', 'annual', 'tier_3_ios'],
        'tier_3_annual' => ['premium', 'annual', 'tier_3_annual'],
        'tier_3_annual:tier-3-annual' => ['premium', 'annual', 'tier_3_annual'],
        'tier_3:tier-3-annual' => ['premium', 'annual', 'tier_3'],
        'tier_3_lifetime' => ['premium', 'lifetime', 'tier_3_lifetime'],
    ];

    public function resolve(array $identifiers, ?string $basePlan, ?string $packageHint): ?array
    {
        $matches = [];

        foreach ($identifiers as $identifier) {
            if (! is_string($identifier) || $identifier === '') continue;
            $identifier = strtolower(trim($identifier));

            foreach (self::PRODUCTS as $catalogId => [$package, $path, $storeProduct]) {
                if ($identifier !== $catalogId && $identifier !== $storeProduct) continue;
                if ($basePlan !== null && $basePlan !== '' && ! $this->basePlanMatches($path, $storeProduct, $basePlan)) continue;
                if ($packageHint !== null && $packageHint !== $package) continue;

                $matches[$package . '|' . $path . '|' . $storeProduct] = [
                    'package' => $package,
                    'path' => $path,
                    'product_id' => $path === 'lifetime' ? $storeProduct : $storeProduct . ':' . $path,
                    'store_product_id' => $storeProduct,
                    'base_plan_id' => $path === 'lifetime' ? null : $path,
                ];
            }
        }

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    private function basePlanMatches(string $path, string $storeProduct, string $basePlan): bool
    {
        if ($path === 'lifetime') return strtolower($basePlan) === 'lifetime';

        $allowedPlans = match ($storeProduct) {
            'standard_android', 'premium_android', 'tier_2_ios', 'tier_3_ios' => $path === 'monthly' ? ['monthly'] : ['annual'],
            'tier_2' => $path === 'monthly' ? ['monthly', 'tier2'] : ['annual', 'tier-2-annual'],
            'tier_3' => $path === 'monthly' ? ['monthly', 'tier3'] : ['annual', 'tier-3-annual'],
            'tier_2_annual' => ['annual', 'tier-2-annual'],
            'tier_3_annual' => ['annual', 'tier-3-annual'],
            default => [],
        };

        return in_array(strtolower($basePlan), $allowedPlans, true);
    }
}
