<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;

/**
 * Stops Docker containers
 */
class Stop
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
     * Stops Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function stop(): void {
        foreach ($this->client->getConfig()->dockermeta as $type => $info) {
            passthru('docker stop ' . $info['containers']['wordpress']);
            passthru('docker stop ' . $info['containers']['db']);
        }
    }
}
