<?php
namespace Aivec\WordPress\CodeceptDocker;

/**
 * Utility methods
 */
class Utils {

    /**
     * Copied from Composer source
     *
     * @return bool Whether the host machine is running a Windows OS
     */
    public static function isWindows() {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * Copied from Composer source
     *
     * @throws \RuntimeException
     * @return string
     */
    public static function getHomeDir() {
        $home = getenv('COMPOSER_HOME');
        if ($home) {
            return $home;
        }

        if (self::isWindows()) {
            if (!getenv('APPDATA')) {
                throw new \RuntimeException('The APPDATA or COMPOSER_HOME environment variable must be set for composer to run correctly');
            }

            return rtrim(strtr(getenv('APPDATA'), '\\', '/'), '/') . '/Composer';
        }

        $userDir = self::getUserDir();
        if (is_dir($userDir . '/.composer')) {
            return $userDir . '/.composer';
        }

        if (self::useXdg()) {
            // XDG Base Directory Specifications
            $xdgConfig = getenv('XDG_CONFIG_HOME') ?: $userDir . '/.config';

            return $xdgConfig . '/composer';
        }

        return $userDir . '/.composer';
    }

    /**
     * Copied from Composer source
     *
     * @return string
     */
    public static function getCacheDir() {
        $home = self::getHomeDir();
        $cacheDir = getenv('COMPOSER_CACHE_DIR');
        if ($cacheDir) {
            return $cacheDir;
        }

        $homeEnv = getenv('COMPOSER_HOME');
        if ($homeEnv) {
            return $homeEnv . '/cache';
        }

        if (self::isWindows()) {
            if ($cacheDir = getenv('LOCALAPPDATA')) {
                $cacheDir .= '/Composer';
            } else {
                $cacheDir = $home . '/cache';
            }

            return rtrim(strtr($cacheDir, '\\', '/'), '/');
        }

        $userDir = self::getUserDir();
        if ($home === $userDir . '/.composer' && is_dir($home . '/cache')) {
            return $home . '/cache';
        }

        if (self::useXdg()) {
            $xdgCache = getenv('XDG_CACHE_HOME') ?: $userDir . '/.cache';

            return $xdgCache . '/composer';
        }

        return $home . '/cache';
    }

    /**
     * Copied from Composer source
     *
     * @return bool
     */
    private static function useXdg() {
        foreach (array_keys($_SERVER) as $key) {
            if (substr($key, 0, 4) === 'XDG_') {
                return true;
            }
        }

        return false;
    }

    /**
     * Copied from Composer source
     *
     * @throws \RuntimeException
     * @return string
     */
    private static function getUserDir() {
        $home = getenv('HOME');
        if (!$home) {
            throw new \RuntimeException('The HOME or COMPOSER_HOME environment variable must be set for composer to run correctly');
        }

        return rtrim(strtr($home, '\\', '/'), '/');
    }
}
