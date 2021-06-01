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
        $pluginsdir = Config::PLUGINS_DIR;
        $themesdir = Config::THEMES_DIR;
        $dirname = self::getWorkingDirname();
        $cdto = Config::WPROOT;
        $cdto = $this->config->projectType === 'theme' ? "{$themesdir}/{$dirname}" : "{$pluginsdir}/{$dirname}";
        passthru("docker exec -i {$this->config->container} /bin/bash -c 'cd {$cdto} && {$command}'");
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
            $this->wpcommand('help');
            return;
        }
        if ($firstc === 'help') {
            $this->wpcommand($command);
            return;
        }
        $this->wpcommand($command);
    }

    /**
     * Spins-up wp-cli container and executes command for acceptance install
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function wpcommand(string $command): void {
        $this->dockerExec("wp {$command}");
    }

    /**
     * Passes command as is to codecept script in Docker container
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command raw codecept command
     * @return void
     */
    public function codecept(string $command): void {
        $this->dockerExec("./vendor/bin/codecept {$command}");
    }

    /**
     * Passes command as is to the codecept script from the host machine
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function codeceptFromHost(string $command): void {
        passthru("./vendor/bin/codecept {$command}");
    }

    /**
     * Returns directory name of project folder
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public static function getWorkingDirname(): string {
        return basename(getcwd());
    }

    /**
     * Returns absolute path of caller directory
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public static function getAbsPath(): string {
        return getcwd();
    }
}
