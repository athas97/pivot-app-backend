<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChallengePacksWebhook;
use App\Models\User;
use App\Services\Membership\MembershipAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChallengeReservationService
{
    private const RESERVATION_MINUTES = 30;

    public function __construct(private readonly MembershipAccessService $membershipAccess) {}

    public function reserve(User $user, array $data, string $idempotencyKey): array
    {
        $fingerprint = $this->fingerprint($data);

        return DB::transaction(function () use ($user, $data, $idempotencyKey, $fingerprint) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $existing = DB::table('challenge_quota_reservations')
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                if ($existing->request_fingerprint !== $fingerprint) abort(409, 'Idempotency key was reused with a different reservation request.');

                return $this->response($existing);
            }

            $duplicate = DB::table('challenge_quota_reservations')->where('reservation_id', $data['reservation_id'])->first();
            if ($duplicate) abort(409, 'Reservation ID is already in use.');

            $this->assertChallengeAccess($user, (int) $data['challenge_id']);
            $now = Carbon::now('UTC');
            $dayKey = $now->toDateString();

            if ($data['allocation'] === 'included') {
                $limit = $this->membershipAccess->snapshot($user)['limits']['daily_challenges'];
                $used = DB::table('challenge_quota_reservations')
                    ->where('user_id', $user->id)
                    ->where('day_key', $dayKey)
                    ->where('allocation', 'included')
                    ->where(function ($query) use ($now) {
                        $query->where('status', 'completed')
                            ->orWhere(function ($query) use ($now) {
                                $query->where('status', 'reserved')->where('expires_at', '>', $now);
                            });
                    })->count();
                if ($used >= $limit) abort(409, 'The account has used or reserved its included daily challenge allowance.');
            } else {
                $pack = ChallengePacksWebhook::query()
                    ->where('user_id', $user->id)
                    ->where('transaction_id', $data['pack_transaction_id'])
                    ->lockForUpdate()
                    ->first();
                if (! $pack || $pack->remaining < 1) abort(409, 'No unused challenge-pack slot is available for this account.');

                $held = DB::table('challenge_quota_reservations')
                    ->where('user_id', $user->id)
                    ->where('pack_transaction_id', $data['pack_transaction_id'])
                    ->where('status', 'reserved')
                    ->where('expires_at', '>', $now)
                    ->count();
                if ($pack->remaining <= $held) abort(409, 'All remaining challenge-pack slots are already reserved.');
            }

            $expiresAt = $now->copy()->addMinutes(self::RESERVATION_MINUTES);
            DB::table('challenge_quota_reservations')->insert([
                'reservation_id' => $data['reservation_id'],
                'user_id' => $user->id,
                'challenge_id' => $data['challenge_id'],
                'allocation' => $data['allocation'],
                'pack_transaction_id' => $data['pack_transaction_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'day_key' => $dayKey,
                'day_timezone' => 'UTC',
                'status' => 'reserved',
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->response(DB::table('challenge_quota_reservations')->where('reservation_id', $data['reservation_id'])->first());
        });
    }

    public function resume(User $user, string $reservationId, int $challengeId): array
    {
        return DB::transaction(function () use ($user, $reservationId, $challengeId) {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $reservation = DB::table('challenge_quota_reservations')
                ->where('user_id', $user->id)
                ->where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->first();
            if (! $reservation) abort(404, 'Reservation not found.');
            if ((int) $reservation->challenge_id !== $challengeId) abort(409, 'Challenge ID does not match this reservation.');
            if ($reservation->status !== 'reserved' || Carbon::parse($reservation->expires_at)->isPast()) {
                abort(409, 'This challenge reservation is no longer active.');
            }

            $this->assertChallengeAccess($user, (int) $reservation->challenge_id);

            return $this->response($reservation);
        });
    }

    public function complete(User $user, string $reservationId, string $completionId): array
    {
        return DB::transaction(function () use ($user, $reservationId, $completionId) {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $reservation = DB::table('challenge_quota_reservations')
                ->where('user_id', $user->id)
                ->where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->first();
            if (! $reservation) abort(404, 'Reservation not found.');
            if ($reservation->status === 'completed') {
                if ($reservation->completion_id !== $completionId) abort(409, 'Reservation was completed with a different completion ID.');

                return json_decode($reservation->completion_response, true);
            }
            if ($reservation->status !== 'reserved' || Carbon::parse($reservation->expires_at)->isPast()) {
                abort(409, 'This challenge reservation is no longer active.');
            }
            if (DB::table('challenge_quota_reservations')->where('completion_id', $completionId)->exists()) {
                abort(409, 'Completion ID is already associated with another reservation.');
            }

            $this->assertChallengeAccess($user, (int) $reservation->challenge_id);
            if ($reservation->allocation === 'pack') {
                $pack = ChallengePacksWebhook::query()
                    ->where('user_id', $user->id)
                    ->where('transaction_id', $reservation->pack_transaction_id)
                    ->lockForUpdate()
                    ->first();
                if (! $pack || $pack->remaining < 1) abort(409, 'The reserved challenge-pack slot is no longer available.');

                $remaining = $pack->remaining - 1;
                $pack->update([
                    'remaining' => $remaining,
                    'status' => $remaining === 0 ? 'used' : $pack->status,
                ]);
            }

            $response = [
                'reservation_id' => $reservation->reservation_id,
                'challenge_id' => (int) $reservation->challenge_id,
                'allocation' => $reservation->allocation,
                'day_key' => $reservation->day_key,
                'day_timezone' => $reservation->day_timezone,
                'expires_at' => Carbon::parse($reservation->expires_at)->utc()->toISOString(),
                'status' => 'completed',
                'completion_id' => $completionId,
            ];
            DB::table('challenge_quota_reservations')->where('id', $reservation->id)->update([
                'status' => 'completed',
                'completion_id' => $completionId,
                'completion_response' => json_encode($response),
                'updated_at' => now(),
            ]);

            return $response;
        });
    }

    public function abandon(User $user, string $reservationId): array
    {
        return DB::transaction(function () use ($user, $reservationId) {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $reservation = DB::table('challenge_quota_reservations')
                ->where('user_id', $user->id)
                ->where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->first();
            if (! $reservation) abort(404, 'Reservation not found.');
            if ($reservation->status === 'completed') abort(409, 'A completed challenge-pack use cannot be refunded.');

            if ($reservation->status === 'reserved') {
                DB::table('challenge_quota_reservations')->where('id', $reservation->id)->update([
                    'status' => 'abandoned',
                    'updated_at' => now(),
                ]);
                $reservation->status = 'abandoned';
            }

            return $this->response($reservation);
        });
    }

    private function assertChallengeAccess(User $user, int $challengeId): void
    {
        $activity = Activity::query()->findOrFail($challengeId);
        $snapshot = $this->membershipAccess->snapshot($user);
        $maxTier = $snapshot['effective_package'] === 'premium' ? 3 : ($snapshot['effective_package'] === 'standard' ? 2 : 1);
        $allowedCategories = $snapshot['effective_package'] === 'standard' && ! $snapshot['legacy_benefits']['all_categories']
            ? $snapshot['selected_category_ids']
            : null;

        if ((int) $activity->tier > $maxTier
            || ($allowedCategories !== null && ! in_array((string) $activity->hobby_id, $allowedCategories, true))) {
            abort(403, 'Current membership does not allow this challenge.');
        }
    }

    private function response(object $reservation): array
    {
        $response = [
            'reservation_id' => $reservation->reservation_id,
            'challenge_id' => (int) $reservation->challenge_id,
            'allocation' => $reservation->allocation,
            'day_key' => $reservation->day_key,
            'day_timezone' => $reservation->day_timezone,
            'expires_at' => Carbon::parse($reservation->expires_at)->utc()->toISOString(),
            'status' => $reservation->status,
        ];
        if ($reservation->allocation === 'pack') $response['pack_transaction_id'] = $reservation->pack_transaction_id;
        if ($reservation->completion_id) $response['completion_id'] = $reservation->completion_id;

        return $response;
    }

    private function fingerprint(array $data): string
    {
        return hash('sha256', json_encode([
            'challenge_id' => (int) $data['challenge_id'],
            'reservation_id' => $data['reservation_id'],
            'allocation' => $data['allocation'],
            'pack_transaction_id' => $data['pack_transaction_id'] ?? null,
        ]));
    }
}
