<?php
namespace Aivec\WordPress\Codeception\CLI;

use Aivec\WordPress\Codeception\CodeceptDocker;

/**
 * Creates codecept-docker.json config file in project root
 */
class CreateConfig {

    /**
     * Runs command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $project_type
     * @param string $namespace
     * @return void
     */
    public static function run($project_type, $namespace = '') {
        CodeceptDocker::validateProjectType($project_type);
        $config = [
            'namespace' => !empty($namespace) ? $namespace : CodeceptDocker::getWorkingDirname(),
            'projectType' => $project_type,
            'language' => 'en',
            'downloadPlugins' => [],
            'ftp' => [],
        ];

        @file_put_contents(
            CodeceptDocker::getAbsPath() . '/codecept-docker.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
}
