<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;

/**
 * Creates codecept-docker.json config file in project root
 */
class CreateConfig
{
    /**
     * Runs command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $project_type
     * @param string $namespace
     * @return void
     */
    public static function createConfig(string $project_type, string $namespace = ''): void {
        ConfigValidator::validateProjectType($project_type);
        $config = [
            'namespace' => !empty($namespace) ? $namespace : Client::getWorkingDirname(),
            'projectType' => $project_type,
            'wordpressVersion' => 'latest',
            'language' => 'en_US',
            'downloadPlugins' => [],
            'downloadThemes' => [],
            'ssh' => [],
            'ftp' => [],
        ];

        @file_put_contents(
            Client::getAbsPath() . '/codecept-docker.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
}
