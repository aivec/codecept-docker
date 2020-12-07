<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

/**
 * Updates images
 */
class Update
{
    /**
     * Updates wordpress:latest and wordpress:cli images
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function updateImages(): void {
        passthru('docker image pull wordpress');
        passthru('docker image pull wordpress:cli');
    }
}
