<?php

namespace App\Organization\Repositories\Contracts;

use App\Organization\Enums\OrganizationStatus;
use App\Organization\Models\Organization;

interface OrganizationRepositoryInterface
{
    public function updateStatus(Organization $organization, OrganizationStatus $status): Organization;

    public function delete(Organization $organization): void;
}
