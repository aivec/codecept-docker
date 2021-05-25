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
     * XDebug port for all environments
     *
     * @var int
     */
    public const XDEBUG_PORT = 4400;

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
     * WordPress version for Docker image
     *
     * @var string
     */
    public $wordpressVersion = 'latest';

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
    public $language = 'en';

    /**
     * Whether to setup selenoid for Selenium testing
     *
     * @var bool
     */
    public $useSelenoid = true;

    /**
     * Selenoid container port number
     *
     * @var int
     */
    public $selenoidPort = 4444;

    /**
     * Validates config and sets member variables
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $conf
     */
    public function __construct($conf) {
        if (!empty($conf['ssh'])) {
            $index = 0;
            foreach ($conf['ssh'] as $ssh) {
                $conf['ssh'][$index]['privateKeyFilename'] = basename($ssh['privateKeyPath']);
                $index++;
            }
        }

        $this->namespace = !empty($conf['namespace']) ? $conf['namespace'] : CLI\Client::getWorkingDirname();
        $this->projectType = $conf['projectType'];
        $this->wordpressVersion = !empty($conf['wordpressVersion']) ? $conf['wordpressVersion'] : $this->wordpressVersion;
        $this->useSelenoid = isset($conf['useSelenoid']) ? $conf['useSelenoid'] : $this->useSelenoid;
        $this->language = isset($conf['language']) ? $conf['language'] : $this->language;
        $this->ftp = !empty($conf['ftp']) ? $conf['ftp'] : $this->ftp;
        $this->ssh = !empty($conf['ssh']) ? $conf['ssh'] : $this->ssh;
        $this->downloadPlugins = !empty($conf['downloadPlugins']) ? $conf['downloadPlugins'] : $this->downloadPlugins;
        $this->downloadThemes = !empty($conf['downloadThemes']) ? $conf['downloadThemes'] : $this->downloadThemes;

        $this->network = $this->namespace . '_wpcodecept-network';
        $types = ['acceptance', 'integration'];
        foreach ($types as $type) {
            $this->dockermeta[$type]['containers']['db'] = $this->namespace . '_wp_' . $type . '_tests_mysqldb';
            $this->dockermeta[$type]['containers']['wordpress'] = $this->namespace . '-' . $type . '-tests-wordpress';
            $this->dockermeta[$type]['volumes']['db'] = $this->namespace . '_wp_' . $type . '_db';
            $this->dockermeta[$type]['dbname'] = $type . '_tests';
            $this->dockermeta[$type]['xdebugport'] = self::XDEBUG_PORT;
        }

        $this->conf['namespace'] = $this->namespace;
        $this->conf['projectType'] = $this->projectType;
        $this->conf['wordpressVersion'] = $this->wordpressVersion;
        $this->conf['useSelenoid'] = $this->useSelenoid;
        $this->conf['language'] = $this->language;
        $this->conf['ftp'] = $this->ftp;
        $this->conf['ssh'] = $this->ssh;
        $this->conf['downloadPlugins'] = $this->downloadPlugins;
        $this->conf['downloadThemes'] = $this->downloadThemes;
    }

    /**
     * Returns config template
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public static function getConfigTemplate(): array {
        return [
            'namespace' => '',
            'projectType' => '',
            'wordpressVersion' => 'latest',
            'useSelenoid' => true,
            'language' => 'en_US',
            'ftp' => [],
            'ssh' => [],
            'downloadPlugins' => [],
            'downloadThemes' => [],
        ];
    }
}
