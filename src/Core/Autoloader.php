<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class Autoloader
{
    const PREFIX = 'StudyMentor\\ContentEngine\\';

    public static function register($sourceDirectory)
    {
        $baseDirectory = realpath($sourceDirectory);

        if ($baseDirectory === false || !is_dir($baseDirectory)) {
            return false;
        }

        spl_autoload_register(
            static function ($className) use ($baseDirectory) {
                if (strpos($className, self::PREFIX) !== 0) {
                    return;
                }

                $relativeClass = substr($className, strlen(self::PREFIX));

                if (
                    $relativeClass === ''
                    || preg_match('/^[A-Za-z0-9_\\\\]+$/', $relativeClass) !== 1
                ) {
                    return;
                }

                $candidate = $baseDirectory
                    . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                    . '.php';
                $resolved = realpath($candidate);
                $requiredPrefix = $baseDirectory . DIRECTORY_SEPARATOR;

                if (
                    $resolved === false
                    || strpos($resolved, $requiredPrefix) !== 0
                    || !is_file($resolved)
                ) {
                    return;
                }

                require_once $resolved;
            }
        );

        return true;
    }
}
