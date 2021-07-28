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
            Logger::configError($e);
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
        Logger::info('Saving snapshot to ' . Logger::yellow("{$workingdir}/tests/_data/{$image}.tar"));
        Logger::info('This might take a while...');
        $output = null;
        $rcode = null;
        exec("docker commit --change='CMD [\"{$scriptsdir}/run.sh\"]' {$conf->container} {$image}:latest", $output, $rcode);
        if ($rcode > 0) {
            Logger::error('Failed creating snapshot');
            exit(1);
        }
        if (isset($output[0])) {
            echo $output[0] . "\n";
        }

        $output = null;
        $rcode = null;
        exec("docker image save -o tests/_data/{$image}.tar {$image}", $output, $rcode);
        if ($rcode > 0) {
            Logger::error('Failed creating snapshot');
            exit(1);
        }
        if (isset($output[0])) {
            echo $output[0] . "\n";
        }

        Logger::info('Success');
        Logger::info('In ' . Logger::green('codecept-docker.json') . ', set ' . Logger::green('image') . ' to ' . Logger::yellow("tests/_data/{$image}.tar"));
    }
}
