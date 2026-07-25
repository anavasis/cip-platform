<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class Requirements
{
    public static function check()
    {
        $messages = array();

        if (version_compare(PHP_VERSION, SMCE_MIN_PHP, '<')) {
            $messages[] = sprintf(
                'StudyMentor Content Engine requires PHP %s or later.',
                SMCE_MIN_PHP
            );
        }

        $wordpressVersion = isset($GLOBALS['wp_version'])
            ? (string) $GLOBALS['wp_version']
            : '';

        if (
            $wordpressVersion === ''
            || version_compare($wordpressVersion, SMCE_MIN_WP, '<')
        ) {
            $messages[] = sprintf(
                'StudyMentor Content Engine requires WordPress %s or later.',
                SMCE_MIN_WP
            );
        }

        return array(
            'met' => $messages === array(),
            'message' => implode(' ', $messages),
        );
    }

    public static function registerAdminNotice($message)
    {
        add_action(
            'admin_notices',
            static function () use ($message) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        );
    }
}
