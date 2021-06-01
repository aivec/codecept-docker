<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\CLI\Runner;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;

/**
 * Save snapshot command
 */
class SaveSnapshot implements Runner
{
    /**
     * Client object
     *
     * @var Client
     */
    public $client;

    /**
     * Initializes command
     *
     * @param array $conf
     */
    public function __construct(array $conf) {
        $this->client = new Client(new Config($conf));
    }

    /**
     * Runs the command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function run(): void {
        try {
            ConfigValidator::validateConfig($this->client->getConfig()->conf);
            $this->saveSnapshot();
        } catch (InvalidConfigException $e) {
            (new Logger())->configError($e);
            exit(1);
        }
    }

    /**
     * Saves the container as an image and exports it as a TAR archive to `tests/_data`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function saveSnapshot() {
        $scriptsdir = Config::AVC_SCRIPTS_DIR;
        $conf = $this->client->getConfig();
        $workingdir = Client::getAbsPath();
        if (!is_dir("{$workingdir}/tests/_data")) {
            mkdir('tests/_data', 0755, true);
        }
        $image = "{$conf->container}-{$conf->phpVersion}";
        passthru("docker commit --change='CMD [\"{$scriptsdir}/run.sh\"]' {$conf->container} {$image}:latest");
        passthru("docker image save -o tests/_data/{$image}.tar {$image}");
    }
}
