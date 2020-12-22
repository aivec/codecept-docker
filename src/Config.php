<?php

namespace Aivec\WordPress\CodeceptDocker;

/**
 * Holds configuration objects and convenience methods for CLI classes
 */
class Config
{

    /**
     * Path to codecept-docker vendor directory
     *
     * @var string
     */
    public const VENDORDIR = '/vendor/aivec/codecept-docker';

    /**
     * WordPress install root directory for Docker containers
     *
     * @var string
     */
    public const WPROOT = '/var/www/html';

    /**
     * Directory for scripts used during container creation
     *
     * @var string
     */
    public const EXTRASDIR = self::WPROOT . '/extras';

    /**
     * Associative array parsed from codecept-docker.json configuration file
     *
     * @var array
     */
    public $conf = null;

    /**
     * Containers prefix
     *
     * @var string
     */
    public $namespace;

    /**
     * Project type. One of 'library', 'plugin', or 'theme'
     *
     * @var string
     */
    public $projectType;

    /**
     * Network for Docker containers
     *
     * @var string
     */
    public $network;

    /**
     * Holds meta information related to containers
     *
     * @var array
     */
    public $dockermeta = [];

    /**
     * SSH configs of plugins/themes to download
     *
     * @var array
     */
    public $ssh = [];

    /**
     * FTP configs of plugins/themes to download
     *
     * @var array
     */
    public $ftp = [];

    /**
     * Plugins to download with wp-cli
     *
     * @var array
     */
    public $downloadPlugins = [];

    /**
     * Themes to download with wp-cli
     *
     * @var array
     */
    public $downloadThemes = [];

    /**
     * Default language to activate
     *
     * @var string
     */
    public $lang = 'en';

    /**
     * Port map for xdebug
     *
     * @var int[]
     */
    public $xdebugPortMap = [
        'acceptance' => 4300,
        'integration' => 4400,
    ];

    /**
     * Grab configuration file contents
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     */
    public function __construct() {
        $this->conf = json_decode(file_get_contents(CLI\Client::getAbsPath() . '/codecept-docker.json'), true);

        (new ConfigValidator($this))->validateConfig();
        $this->namespace = !empty($this->conf['namespace']) ? $this->conf['namespace'] : CLI\Client::getWorkingDirname();
        $this->projectType = $this->conf['projectType'];
        $this->lang = !empty($this->conf['language']) ? $this->conf['language'] : $this->lang;
        $this->ftp = !empty($this->conf['ftp']) ? $this->conf['ftp'] : $this->ftp;
        $this->ssh = !empty($this->conf['ssh']) ? $this->conf['ssh'] : $this->ssh;
        $this->downloadPlugins = !empty($this->conf['downloadPlugins']) ? $this->conf['downloadPlugins'] : $this->downloadPlugins;
        $this->downloadThemes = !empty($this->conf['downloadThemes']) ? $this->conf['downloadThemes'] : $this->downloadThemes;

        $this->network = $this->namespace . '_wpcodecept-network';
        $types = ['acceptance', 'integration'];
        foreach ($types as $type) {
            $this->dockermeta[$type]['containers']['db'] = $this->namespace . '_wp_' . $type . '_tests_mysqldb';
            $this->dockermeta[$type]['containers']['wordpress'] = $this->namespace . '-' . $type . '-tests-wordpress';
            $this->dockermeta[$type]['volumes']['db'] = $this->namespace . '_wp_' . $type . '_db';
            $this->dockermeta[$type]['dbname'] = $type . '_tests';
            $this->dockermeta[$type]['xdebugport'] = $this->xdebugPortMap[$type];
        }
    }
}
