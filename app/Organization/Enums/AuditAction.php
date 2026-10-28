<?php

namespace App\Organization\Enums;

/**
 * Actions written to organization_audit_logs. Later phases add their own cases here.
 */
enum AuditAction: string
{
    case PIVOT_ROLE_GRANTED = 'pivot_role.granted';
    case PIVOT_ROLE_REVOKED = 'pivot_role.revoked';

    case ORGANIZATION_ROLE_ASSIGNED = 'organization_role.assigned';
    case ORGANIZATION_ROLE_REVOKED = 'organization_role.revoked';

    case ORGANIZATION_SUSPENDED = 'organization.suspended';
    case ORGANIZATION_REACTIVATED = 'organization.reactivated';
    case ORGANIZATION_REMOVED = 'organization.removed';

    case TENANT_ACCESS_DENIED = 'tenant.access_denied';
}
