<?php

namespace App\Organization\Repositories;

use App\Organization\Enums\MembershipStatus;
use App\Organization\Models\OrganizationMembership;
use App\Organization\Repositories\Contracts\OrganizationMembershipRepositoryInterface;

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
}
