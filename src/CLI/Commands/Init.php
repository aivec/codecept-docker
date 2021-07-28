<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\CLI\Runner;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;

/**
 * Init command
 */
class Init implements Runner
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
            $this->init();
        } catch (InvalidConfigException $e) {
            Logger::configError($e);
            exit(1);
        }
    }

    /**
     * Initializes Docker containers and creates Codeception scaffolding
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        (new CreateScaffolding($this->client->getConfig()->conf))->createScaffolding();
        (new Up($this->client->getConfig()->conf))->up();
    }
}
