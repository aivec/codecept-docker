<?php

namespace Aivec\WordPress\CodeceptDocker\CLI;

use Aivec\WordPress\CodeceptDocker\Config;

/**
 * CLI Client
 */
class Client
{
    /**
     * Dependency injected config model
     *
     * @var Config
     */
    private $config;

    /**
     * Initializes config member var
     *
     * @param Config $config
     */
    public function __construct(Config $config) {
        $this->config = $config;
    }

    /**
     * Returns config object
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return Config
     */
    public function getConfig(): Config {
        return $this->config;
    }

    /**
     * Wrapper for docker exec
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function dockerExec(string $command): void {
        $path = $this->config->projectType === 'theme' ? Config::WPROOT . '/wp-content/themes/' : Config::WPROOT . '/wp-content/plugins/';
        $path .= self::getWorkingDirname();
        $dockerexec = 'docker exec -i --user 1000:1000 ' . $this->config->dockermeta['integration']['containers']['wordpress'] . ' /bin/bash -c \'cd ' . $path . '&& ';
        passthru($dockerexec . $command . '\'');
    }

    /**
     * Passes command/args to wp-cli container that operates on both acceptance and
     * integration WordPress installs
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $argv
     * @return void
     */
    public function wpcli(array $argv): void {
        $firstc = isset($argv[2]) ? $argv[2] : '';
        $command = isset($argv[2]) ? join(' ', array_slice($argv, 2)) : '';
        if (empty($command)) {
            $this->wpAcceptanceCLI('help');
            return;
        }
        if ($firstc === 'help') {
            $this->wpAcceptanceCLI($command);
            return;
        }
        $this->wpAcceptanceCLI($command);
        $this->wpIntegrationCLI($command);
    }

    /**
     * Spins-up wp-cli container and executes command for acceptance install
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function wpAcceptanceCLI(string $command): void {
        passthru('docker run -i --rm \
            --volumes-from ' . $this->config->dockermeta['acceptance']['containers']['wordpress'] . ' \
            --network ' . $this->config->network . ' \
            --user 33:33 -e HOME=/tmp \
            wordpress:cli ' . $command);
    }

    /**
     * Spins-up wp-cli container and executes command for integration install
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function wpIntegrationCLI(string $command): void {
        passthru('docker run -i --rm \
            --volumes-from ' . $this->config->dockermeta['integration']['containers']['wordpress'] . ' \
            --network ' . $this->config->network . ' \
            --user 33:33 -e HOME=/tmp \
            wordpress:cli ' . $command);
    }

    /**
     * Passes command as is to codecept script in Docker container
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command raw codecept command
     * @return void
     */
    public function codecept(string $command): void {
        $this->dockerExec('./vendor/bin/codecept ' . $command);
    }

    /**
     * Returns directory name of project folder
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public static function getWorkingDirname() {
        return basename(getcwd());
    }

    /**
     * Returns absolute path of caller directory
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public static function getAbsPath() {
        return getcwd();
    }
}
