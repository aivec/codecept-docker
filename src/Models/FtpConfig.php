<?php

namespace Aivec\WordPress\CodeceptDocker\Models;

/**
 * FTP config model
 */
class FtpConfig
{
    /**
     * FTP host
     *
     * @var string
     */
    private $host;

    /**
     * FTP user
     *
     * @var string
     */
    private $user;

    /**
     * FTP password
     *
     * @var string
     */
    private $password;

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
     * Initializes an FTP config object
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $host
     * @param string $user
     * @param string $password
     * @param array  $plugins
     * @param array  $themes
     * @return void
     */
    public function __construct(
        string $host,
        string $user,
        string $password = '',
        array $plugins = [],
        array $themes = []
    ) {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->plugins = $plugins;
        $this->themes = $themes;
    }

    /**
     * Getter for FTP password
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getHost(): string {
        return $this->host;
    }

    /**
     * Getter for FTP user
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getUser(): string {
        return $this->user;
    }

    /**
     * Getter for FTP password
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getPassword(): string {
        return $this->password;
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
