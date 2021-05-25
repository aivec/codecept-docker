<?php

namespace Aivec\WordPress\CodeceptDocker\CLI;

interface Runner
{
    /**
     * Runs a command
     *
     * This method should invoke the CLI command, handle whatever exceptions
     * are thrown, and log any errors.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function run(): void;
}
