<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;

/**
 * Stops and removes Docker containers and shared network
 */
class Down
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
     * Stops and removes Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function down(): void {
        foreach ($this->client->getConfig()->dockermeta as $type => $info) {
            passthru('docker stop ' . $info['containers']['wordpress']);
            passthru('docker stop ' . $info['containers']['db']);
            passthru('docker rm ' . $info['containers']['wordpress']);
            passthru('docker rm ' . $info['containers']['db']);
        }
        passthru('docker network rm ' . $this->client->getConfig()->network);
    }
}
