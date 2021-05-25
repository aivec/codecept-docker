<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;

/**
 * Stops and removes Docker containers and shared network
 */
class Down
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
            $this->down();
        } catch (InvalidConfigException $e) {
            (new Logger())->configError($e);
            exit(1);
        }
    }

    /**
     * Stops and removes Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function down(): void {
        $conf = $this->client->getConfig();
        foreach ($conf->dockermeta as $type => $info) {
            passthru('docker stop ' . $info['containers']['wordpress']);
            passthru('docker stop ' . $info['containers']['db']);
            passthru('docker rm ' . $info['containers']['wordpress']);
            passthru('docker rm ' . $info['containers']['db']);
        }
        if ($conf->useSelenoid) {
            passthru('docker stop ' . $conf->namespace . '_selenoid');
            passthru('docker rm ' . $conf->namespace . '_selenoid');
        }
        passthru('docker network rm ' . $conf->network);
    }
}
