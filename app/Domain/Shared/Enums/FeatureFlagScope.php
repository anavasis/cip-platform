<?php

namespace App\Domain\Shared\Enums;

enum FeatureFlagScope: string
{
    case Global = 'global';
    case Organization = 'organization';
    case Project = 'project';
}
