<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;

/**
 * Starts Docker containers
 */
class Start
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
            $this->start();
        } catch (InvalidConfigException $e) {
            Logger::configError($e);
            exit(1);
        }
    }

    /**
     * Starts stopped Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function start(): void {
        $conf = $this->client->getConfig();
        if ($conf->useSelenoid) {
            passthru("docker start {$conf::$selenoidc}");
        }
        passthru("docker start {$conf->container}");
        passthru("docker start {$conf::$mysqlc}");
        passthru("docker start {$conf::$phpmyadminc}");
    }
}
