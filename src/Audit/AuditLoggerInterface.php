<?php

namespace StudyMentor\ContentEngine\Audit;

defined('ABSPATH') || exit;

interface AuditLoggerInterface
{
    public function record($event, array $context = array());
}
