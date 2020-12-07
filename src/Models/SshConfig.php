<?php

namespace Aivec\WordPress\CodeceptDocker\Models;

/**
 * SSH config model
 */
class SshConfig
{
    /**
     * SSH host
     *
     * @var string
     */
    private $host;

    /**
     * SSH user
     *
     * @var string
     */
    private $user;

    /**
     * Relative or absolute path to SSH private key file
     *
     * @var string
     */
    private $privateKeyPath;

    /**
     * List of plugins to download
     *
     * @var array
     */
    private $plugins;

    /**
     * List of themes to download
     *
     * @var array
     */
    private $themes;

    /**
     * Initializes an SSH config object
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $host
     * @param string $user
     * @param string $privateKeyPath
     * @param array  $plugins
     * @param array  $themes
     * @return void
     */
    public function __construct(
        string $host,
        string $user,
        string $privateKeyPath,
        array $plugins = [],
        array $themes = []
    ) {
        $this->host = $host;
        $this->user = $user;
        $this->privateKeyPath = $privateKeyPath;
        $this->plugins = $plugins;
        $this->themes = $themes;
    }

    /**
     * Getter for SSH password
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getHost(): string {
        return $this->host;
    }

    /**
     * Getter for SSH user
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getUser(): string {
        return $this->user;
    }

    /**
     * Getter for SSH private key path
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getPrivateKeyPath(): string {
        return $this->privateKeyPath;
    }

    /**
     * Getter for list of plugins
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getPlugins(): array {
        return $this->plugins;
    }

    /**
     * Getter for list of themes
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getThemes(): array {
        return $this->themes;
    }
}
