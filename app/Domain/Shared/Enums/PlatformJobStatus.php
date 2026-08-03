<?php

namespace App\Domain\Shared\Enums;

enum PlatformJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
