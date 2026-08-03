<?php

namespace App\Domain\Shared\Enums;

enum RoleScope: string
{
    case Organization = 'organization';
    case Project = 'project';
}
