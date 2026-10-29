<?php

namespace App\Organization\Repositories\Contracts;

use App\Organization\Models\OrganizationMembership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface OrganizationMembershipRepositoryInterface
{
    public function isActiveMember(string $organizationId, int $userId): bool;

    public function countActive(string $organizationId): int;

    /**
     * Expire every active membership of the organization. Returns the number expired.
     */
    public function expireActive(string $organizationId, string $reason, Carbon $at): int;

    /**
     * Memberships expired by a contract expiry that may be restored: the user is not active
     * in another organization and the membership's own expires_at has not passed.
     * Oldest members first.
     *
     * @return Collection<int, OrganizationMembership>
     */
    public function restorableAfterContractExpiry(string $organizationId, Carbon $at): Collection;

    /**
     * @param  list<int>  $membershipIds
     */
    public function restore(array $membershipIds, Carbon $at): int;
}
