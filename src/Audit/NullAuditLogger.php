<?php

namespace StudyMentor\ContentEngine\Audit;

defined('ABSPATH') || exit;

final class NullAuditLogger implements AuditLoggerInterface
{
    public function record($event, array $context = array())
    {
    }
}
