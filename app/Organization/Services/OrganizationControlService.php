<?php

namespace App\Organization\Services;

use App\Models\User;
use App\Organization\Enums\AuditAction;
use App\Organization\Enums\OrganizationStatus;
use App\Organization\Exceptions\OrganizationActionException;
use App\Organization\Models\Organization;
use App\Organization\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * The Pivot control boundary: Super Admin can always suspend, reactivate or remove any organization.
 * Normal organization lifecycle (create, edit, renew, expire) belongs to OrganizationService (B3).
 */
class OrganizationControlService
{
    public function __construct(
        protected OrganizationRepositoryInterface $organizations,
        protected AuditLogger $audit,
    ) {}

    public function suspend(Organization $organization, User $actor, string $reason): Organization
    {
        if ($organization->status === OrganizationStatus::SUSPENDED) {
            throw OrganizationActionException::invalidStatusTransition($organization->status, 'suspended');
        }

        return $this->changeStatus($organization, OrganizationStatus::SUSPENDED, AuditAction::ORGANIZATION_SUSPENDED, $actor, $reason);
    }

    public function reactivate(Organization $organization, User $actor, string $reason): Organization
    {
        if ($organization->status !== OrganizationStatus::SUSPENDED) {
            throw OrganizationActionException::invalidStatusTransition($organization->status, 'reactivated');
        }

        return $this->changeStatus($organization, OrganizationStatus::ACTIVE, AuditAction::ORGANIZATION_REACTIVATED, $actor, $reason);
    }

    public function remove(Organization $organization, User $actor, string $reason): void
    {
        DB::transaction(function () use ($organization, $actor, $reason) {
            $this->organizations->delete($organization);

            $this->audit->record(
                action: AuditAction::ORGANIZATION_REMOVED,
                organization: $organization,
                target: $organization,
                metadata: ['reason' => $reason],
                actor: $actor,
            );
        });
    }

    private function changeStatus(
        Organization $organization,
        OrganizationStatus $status,
        AuditAction $action,
        User $actor,
        string $reason,
    ): Organization {
        $previous = $organization->status;

        return DB::transaction(function () use ($organization, $status, $action, $actor, $reason, $previous) {
            $this->organizations->updateStatus($organization, $status);

            $this->audit->record(
                action: $action,
                organization: $organization,
                target: $organization,
                metadata: [
                    'previous_status' => $previous->value,
                    'new_status' => $status->value,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return $organization;
        });
    }
}
