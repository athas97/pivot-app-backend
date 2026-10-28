<?php

namespace App\Organization\Repositories\Contracts;

interface OrganizationMembershipRepositoryInterface
{
    public function isActiveMember(string $organizationId, int $userId): bool;
}
