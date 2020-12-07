<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;

/**
 * Starts Docker containers
 */
class Start
{
    /**
     * Dependency injected client
     *
     * @var Client
     */
    private $client;

    /**
     * Injects client
     *
     * @param Client $client
     */
    public function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * Starts stopped Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function start(): void {
        foreach ($this->client->getConfig()->dockermeta as $type => $info) {
            passthru('docker start ' . $info['containers']['db']);
            passthru('docker start ' . $info['containers']['wordpress']);
        }
    }
}
