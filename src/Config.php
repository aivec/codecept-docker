<?php

namespace Aivec\WordPress\CodeceptDocker;

/**
 * Holds configuration objects and convenience methods for CLI classes
 */
class Config
{
    /**
     * WordPress install root directory for Docker containers
     *
     * @var string
     */
    public const WPROOT = '/app';
    public const THEMES_DIR = self::WPROOT . '/wp-content/themes';
    public const PLUGINS_DIR = self::WPROOT . '/wp-content/plugins';

    /**
     * Path to codecept-docker vendor directory
     *
     * @var string
     */
    public const VENDORDIR = '/vendor/aivec/codecept-docker';

    public const AVC_META_DIR = '/avc-wpdocker-meta';
    public const AVC_SCRIPTS_DIR = self::AVC_META_DIR . '/scripts';
    public const AVC_DUMPFILES_DIR = self::AVC_META_DIR . '/dumpfiles';
    public const AVC_SSH_DIR = self::AVC_META_DIR . '/ssh';
    public const AVC_USER_SCRIPTS_DIR = self::AVC_META_DIR . '/user-scripts';
    public const AVC_TEMP_DIR = self::AVC_META_DIR . '/temp';
    public const AVC_CACHE_DIR = self::AVC_META_DIR . '/cache';

    /**
     * Network for Docker containers
     *
     * @var string
     */
    public static $network = 'wpcodecept_network';

    /**
     * MySQL container name
     *
     * @var string
     */
    public static $mysqlc = 'wpcodecept_mysqldb';

    /**
     * MySQL volume name
     *
     * @var string
     */
    public static $mysqldbv = 'wpcodecept_dbv';

    /**
     * The phpMyAdmin container name
     *
     * @var string
     */
    public static $phpmyadminc = 'wpcodecept_phpmyadmin';

    /**
     * Selenoid container name
     *
     * @var string
     */
    public static $selenoidc = 'wpcodecept_selenoid';

    /**
     * Selenoid container port number
     *
     * @var int
     */
    public static $selenoidPort = 4444;

    /**
     * Associative array parsed from codecept-docker.json configuration file
     *
     * @var array
     */
    public $conf = null;

    /**
     * WordPress container prefix
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
     * PHP version number. One of `7.2`, `7.3`, `7.4`, or `8.0`
     *
     * @var string
     */
    public $phpVersion = '7.4';

    /**
     * Project type. One of `plugin`, `theme`, or `other`
     *
     * @var string
     */
    public $projectType;

    /**
     * WordPress container name
     *
     * @var string
     */
    public $container;

    /**
     * Relative path to image TAR archive
     *
     * @var null|string
     */
    public $imagePath = null;

    /**
     * Name of the acceptance/functional tests DB
     *
     * @var string
     */
    public $acceptance_dbname;

    /**
     * Name of the integration tests DB
     *
     * @var string
     */
    public $integration_dbname;

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
     * Key-value pairs of arbitrary PHP environment variables
     *
     * @var array
     */
    public $envvars = [];

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
        $this->phpVersion = !empty($conf['phpVersion']) ? $conf['phpVersion'] : $this->phpVersion;
        $this->useSelenoid = isset($conf['useSelenoid']) ? $conf['useSelenoid'] : $this->useSelenoid;
        $this->language = isset($conf['language']) ? $conf['language'] : $this->language;
        $this->imagePath = !empty($conf['imagePath']) ? $conf['imagePath'] : $this->imagePath;
        $this->envvars = isset($conf['envvars']) && is_array($conf['envvars']) ? $conf['envvars'] : $this->envvars;
        $this->ftp = !empty($conf['ftp']) ? $conf['ftp'] : $this->ftp;
        $this->ssh = !empty($conf['ssh']) ? $conf['ssh'] : $this->ssh;
        $this->downloadPlugins = !empty($conf['downloadPlugins']) ? $conf['downloadPlugins'] : $this->downloadPlugins;
        $this->downloadThemes = !empty($conf['downloadThemes']) ? $conf['downloadThemes'] : $this->downloadThemes;
        $this->container = "{$this->namespace}-wpcodecept-wordpress";
        $this->acceptance_dbname = "{$this->namespace}-acceptance";
        $this->integration_dbname = "{$this->namespace}-integration";

        $this->conf['namespace'] = $this->namespace;
        $this->conf['projectType'] = $this->projectType;
        $this->conf['wordpressVersion'] = $this->wordpressVersion;
        $this->conf['phpVersion'] = $this->phpVersion;
        $this->conf['useSelenoid'] = $this->useSelenoid;
        $this->conf['language'] = $this->language;
        $this->conf['imagePath'] = $this->imagePath;
        $this->conf['envvars'] = $this->envvars;
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
            'phpVersion' => '7.4',
            'useSelenoid' => true,
            'language' => 'en_US',
            'imagePath' => '',
            'envvars' => [],
            'ftp' => [],
            'ssh' => [],
            'downloadPlugins' => [],
            'downloadThemes' => [],
        ];
    }
}
