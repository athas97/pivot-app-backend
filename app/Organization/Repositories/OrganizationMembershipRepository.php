<?php

namespace App\Organization\Repositories;

use App\Organization\Enums\MembershipStatus;
use App\Organization\Models\OrganizationMembership;
use App\Organization\Repositories\Contracts\OrganizationMembershipRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrganizationMembershipRepository implements OrganizationMembershipRepositoryInterface
{
    public function isActiveMember(string $organizationId, int $userId): bool
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', MembershipStatus::ACTIVE)
            ->exists();
    }

    public function countActive(string $organizationId): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('status', MembershipStatus::ACTIVE)
            ->count();
    }

    public function expireActive(string $organizationId, string $reason, Carbon $at): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('status', MembershipStatus::ACTIVE)
            ->update([
                'status' => MembershipStatus::EXPIRED,
                'deactivated_at' => $at,
                'deactivation_reason' => $reason,
                'updated_at' => $at,
            ]);
    }

    public function restorableAfterContractExpiry(string $organizationId, Carbon $at): Collection
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('status', MembershipStatus::EXPIRED)
            ->where('deactivation_reason', OrganizationMembership::REASON_CONTRACT_EXPIRED)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $at))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('organization_memberships as other')
                ->whereColumn('other.user_id', 'organization_memberships.user_id')
                ->where('other.status', MembershipStatus::ACTIVE->value))
            ->orderBy('joined_at')
            ->orderBy('id')
            ->get();
    }

    public function restore(array $membershipIds, Carbon $at): int
    {
        if ($membershipIds === []) {
            return 0;
        }

        return OrganizationMembership::query()
            ->whereIn('id', $membershipIds)
            ->where('status', MembershipStatus::EXPIRED)
            ->update([
                'status' => MembershipStatus::ACTIVE,
                'activated_at' => $at,
                'deactivated_at' => null,
                'deactivation_reason' => null,
                'updated_at' => $at,
            ]);
    }
}
