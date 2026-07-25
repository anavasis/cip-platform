<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate()
    {
        $result = Requirements::check();

        if (!$result['met']) {
            wp_die(
                esc_html($result['message']),
                esc_html('StudyMentor Content Engine activation stopped')
            );
        }

        global $wpdb;

        $schemaManager = new SchemaManager(isset($wpdb) ? $wpdb : null);

        if (!$schemaManager->migrate()) {
            wp_die(
                esc_html('Activation failed. Please verify database access and retry.'),
                esc_html('StudyMentor Content Engine activation stopped')
            );
        }
    }
}
