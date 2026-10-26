<?php

namespace App\Organization\Enums;

enum OrganizationRole: string
{
    case ORG_OWNER = 'ORG_OWNER';
    case ORG_ADMIN = 'ORG_ADMIN';
    case ORG_ANALYST = 'ORG_ANALYST';
    case ORG_MEMBER = 'ORG_MEMBER';
}
