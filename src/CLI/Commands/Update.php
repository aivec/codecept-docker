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
     * Updates visiblevc/wordpress:latest image
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function update(): void {
        passthru('docker image pull visiblevc/wordpress');
    }
}
