<?php

namespace App\Organization\Exceptions;

use App\Organization\Enums\OrganizationStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Business-rule failures in the Corporate module. Rendered as { "message": ... } by the API handler.
 */
class OrganizationActionException extends HttpException
{
    public static function notAnActiveMember(): self
    {
        return new self(422, 'The user must be an active member of this organization.');
    }

    public static function roleAlreadyAssigned(): self
    {
        return new self(409, 'The user already has this role in this organization.');
    }

    public static function roleAlreadyRevoked(): self
    {
        return new self(409, 'This role assignment has already been revoked.');
    }

    public static function invalidStatusTransition(OrganizationStatus $current, string $action): self
    {
        return new self(409, "An organization with status '{$current->value}' cannot be {$action}.");
    }
}
