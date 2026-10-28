<?php

namespace App\Organization\Repositories;

use App\Organization\Enums\OrganizationStatus;
use App\Organization\Models\Organization;
use App\Organization\Repositories\Contracts\OrganizationRepositoryInterface;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function updateStatus(Organization $organization, OrganizationStatus $status): Organization
    {
        $organization->update(['status' => $status]);

        return $organization;
    }

    // Soft delete: the record and its audit history are kept.
    public function delete(Organization $organization): void
    {
        $organization->delete();
    }
}
