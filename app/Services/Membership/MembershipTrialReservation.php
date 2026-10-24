<?php

namespace App\Services\Membership;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/** A reservation is a checkout to resume, never evidence that a trial was consumed. */
class MembershipTrialReservation
{
    public function __construct(private readonly MembershipProductCatalog $catalog) {}

    public function forUser(int $userId, ?object $trial): ?array
    {
        if ($trial?->status !== 'reserved') return null;

        $matches = [];
        foreach (DB::table('membership_attempts')->where('user_id', $userId)
            ->where('operation', 'trial')->where('status', 'pending')->get() as $attempt) {
            $quote = json_decode($attempt->quote, true);
            if (! is_array($quote) || ($quote['attempt_id'] ?? null) !== $attempt->attempt_id
                || ! is_string($quote['expires_at'] ?? null) || empty($quote['expires_at'])
                || empty($quote['offer_id'])) continue;
            try {
                Carbon::parse($quote['expires_at']);
            } catch (\Throwable) {
                continue;
            }
            $product = $this->catalog->resolve([$quote['product_id'] ?? null], $quote['base_plan_id'] ?? null, null);
            if (! $product || $product['path'] === 'lifetime') continue;
            $matches[] = [...$quote, 'selected_package' => $product['package'], 'payment_path' => $product['path']];
        }

        // Never guess which checkout owns an account-wide trial reservation.
        return count($matches) === 1 ? $matches[0] : null;
    }
}
