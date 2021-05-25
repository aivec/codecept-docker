<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\CLI\Runner;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;
use Garden\Cli\Args;

/**
 * Creates codecept-docker.json config file in project root
 */
class CreateConfig implements Runner
{
    /**
     * CLI `Args` object
     *
     * @var Args
     */
    public $args;

    /**
     * Initializes command
     *
     * @param Args $args
     */
    public function __construct(Args $args) {
        $this->args = $args;
    }

    /**
     * Runs the command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function run(): void {
        try {
            $config = self::createConfig((string)$this->args->getOpt('type'), (string)$this->args->getOpt('namespace'));
            file_put_contents(
                Client::getAbsPath() . '/codecept-docker.json',
                json_encode($config, JSON_PRETTY_PRINT)
            );
        } catch (InvalidConfigException $e) {
            (new Logger())->configError($e);
            exit(1);
        }
    }

    /**
     * Creates a config and returns it
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $project_type
     * @param string $namespace
     * @return array
     * @throws InvalidConfigException Thrown if `$project_type` is invalid.
     */
    public static function createConfig(string $project_type, string $namespace = ''): array {
        $config = Config::getConfigTemplate();
        $config['namespace'] = !empty($namespace) ? $namespace : Client::getWorkingDirname();
        $config['projectType'] = $project_type;
        ConfigValidator::validateConfig($config);
        if (empty($namespace)) {
            (new Logger())->warn('no "namespace" field provided, defaulting to project directory name');
        }

        return $config;
    }
}
