<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Runner;

/**
 * Updates images
 */
class Update implements Runner
{
    /**
     * Runs the command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function run(): void {
        self::update();
    }

    /**
     * Updates wordpress:latest and wordpress:cli images
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function update(): void {
        passthru('docker image pull wordpress');
        passthru('docker image pull wordpress:cli');
    }
}
