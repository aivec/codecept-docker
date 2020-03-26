<?php
namespace Aivec\WordPress\Codeception;

/**
 * Holds configuration objects and convenience methods for CLI classes
 */
class CodeceptDocker {

    /**
     * Path to codecept-docker vendor directory
     *
     * @var string
     */
    const VENDORDIR = '/vendor/aivec/codecept-docker';

    /**
     * WordPress install root directory for Docker containers
     *
     * @var string
     */
    const WPROOT = '/var/www/html';

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
     * Default language to activate
     *
     * @var string
     */
    public $lang = 'en';

    /**
     * Grab configuration file contents
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     */
    public function __construct() {
        $this->conf = json_decode(file_get_contents(self::getAbsPath() . '/codecept-docker.json'), true);
        
        if (empty($this->conf['namespace'])) {
            echo "\r\n";
            echo 'WARNING: codecept-docker.json does not contain a "namespace" field, defaulting to project directory name as a prefix for containers';
            echo "\r\n";
        }
        $this->namespace = !empty($this->conf['namespace']) ? $this->conf['namespace'] : self::getWorkingDirname();
        $this->projectType = $this->conf['projectType'];
        $this->lang = !empty($this->conf['language']) ? $this->conf['language'] : $this->lang;
        $this->ftp = !empty($this->conf['ftp']) ? $this->conf['ftp'] : $this->ftp;
        $this->downloadPlugins = !empty($this->conf['downloadPlugins']) ? $this->conf['downloadPlugins'] : $this->downloadPlugins;
        
        $this->network = $this->namespace . '_wpcodecept-network';
        $types = ['acceptance', 'integration'];
        foreach ($types as $type) {
            $this->dockermeta[$type]['containers']['db'] = $this->namespace . '_wp_' . $type . '_tests_mysqldb';
            $this->dockermeta[$type]['containers']['wordpress'] = $this->namespace . '-' . $type . '-tests-wordpress';
            $this->dockermeta[$type]['volumes']['db'] = $this->namespace . '_wp_' . $type . '_db';
            $this->dockermeta[$type]['dbname'] = $type . '_tests';
        }
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

    /**
     * Checks whether the CLI was invoked from the composer project root or not
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return boolean
     */
    public static function invokedFromProjectRoot() {
        return file_exists(self::getAbsPath() . self::VENDORDIR . '/composer.json');
    }

    /**
     * Validates value of 'projectType' key in JSON config
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $project_type
     * @return void
     */
    public static function validateProjectType($project_type) {
        if (empty($project_type)) {
            echo "\r\n";
            echo 'FATAL: "projectType" is not defined. "projectType" must be one of "library", "plugin", or "theme"';
            echo "\r\n";
            exit(1);
        }

        if ($project_type !== 'library' && $project_type !== 'plugin' && $project_type !== 'theme') {
            echo "\r\n";
            echo 'FATAL: "projectType" must be one of "library", "plugin", or "theme"';
            echo "\r\n";
            exit(1);
        }
    }
}
