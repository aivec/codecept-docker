<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\CLI\Runner;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;
use Garden\Cli\Args;

/**
 * Bootstrap command
 */
class Bootstrap implements Runner
{
    /**
     * Vendor src dir for this package
     *
     * @var string
     */
    public static $vendordir = './vendor/aivec/codecept-docker';

    /**
     * Step header divider
     *
     * @var string
     */
    public static $stepheader = '----------------';

    /**
     * Client object
     *
     * @var Client
     */
    public $client;

    /**
     * Flag for whether to create `Helper` classes or not
     *
     * @var bool
     */
    public $withHelpers;

    /**
     * Flag for whether to create sample tests or not
     *
     * @var bool
     */
    public $withSampleTests;

    /**
     * Initializes command
     *
     * @param array $conf
     * @param Args  $args
     */
    public function __construct(array $conf, Args $args) {
        $this->client = new Client(new Config($conf));
        $withHelpers = $args->getOpt('with-helpers');
        $withSampleTests = $args->getOpt('with-sample-tests');
        $this->withHelpers = $withHelpers === true ? true : false;
        $this->withSampleTests = $withSampleTests === true ? true : false;
    }

    /**
     * Runs the command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function run(): void {
        try {
            ConfigValidator::validateConfig($this->client->getConfig()->conf);
            $this->bootstrap();
        } catch (InvalidConfigException $e) {
            Logger::configError($e);
            exit(1);
        }
    }

    /**
     * Copies test scaffolding folders and files to project dir if they don't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function bootstrap() {
        // create tests directory (if not exists) or abort
        $this->createTestsDirOrAbort();

        // install necessary modules
        $this->installModules();

        // add codeception test scaffolding
        $this->createScaffolding();

        // create configs
        $this->createConfigFiles();

        // setup selenoid if applicable
        $this->setupSelenoid();

        // generate helpers if necessary
        $this->generateHelpers();

        // generate sample tests if necessary
        $this->generateSampleTests();

        $this->logHeader('Building Codeception modules');
        $this->client->codeceptFromHost('build');

        print "\n";
        Logger::info('Done.');
        Logger::info('-------------------------------------------------------------------');
        Logger::info('Run ' . Logger::green('aivec-codecept up') . ' to create and/or start the Docker containers.');
    }

    /**
     * Creates the `tests` folder or aborts on error
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createTestsDirOrAbort() {
        $workingdir = Client::getAbsPath();
        $target = "{$workingdir}/tests";
        if (!is_dir($target)) {
            $res = @mkdir($target, 0755, true);
            if ($res === false) {
                Logger::error('Could not create ' . Logger::yellow($target) . ' folder. Aborting.');

                print "\nThis is most likely a permissions error. Make sure PHP can write in this directory.\n";
                exit(1);
            }
        }
    }

    /**
     * Installs necessary modules that are not already installed
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installModules() {
        $vconstraint = ':^1.0';
        $this->logHeader('Installing Codeception modules', false);
        $this->installModule('codeception/module-db', $vconstraint);
        $this->installModule('codeception/module-phpbrowser', $vconstraint);
        $this->installModule('codeception/module-cli', $vconstraint);
        $this->installModule('codeception/module-asserts', $vconstraint);
    }

    /**
     * Creates tests directory structure
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createScaffolding() {
        $this->logHeader('Creating directory structure');

        $workingdir = Client::getAbsPath();
        $gitignore = "*\n!.gitignore";

        $this->mkdir("{$workingdir}/tests/_data");
        $this->mkdir("{$workingdir}/tests/_output");
        $this->mkdir("{$workingdir}/tests/_support");
        $this->mkdir("{$workingdir}/tests/_support/_generated");
        $this->filePutContents("{$workingdir}/tests/_data/.gitkeep", '');
        $this->filePutContents("{$workingdir}/tests/_output/.gitkeep", '');
        $this->filePutContents("{$workingdir}/tests/_output/.gitignore", $gitignore);
        $this->filePutContents("{$workingdir}/tests/_support/_generated/.gitignore", $gitignore);
    }

    /**
     * Creates an `.env.testing` file in the project directory if it doesn't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createEnvTestingFile() {
        $config = $this->client->getConfig();
        $workingdir = Client::getAbsPath();
        $wproot = Config::WPROOT;

        $env = [];
        $env[] = "WP_ROOT_FOLDER=\"{$wproot}\"";
        $env[] = 'TEST_SITE_WP_ADMIN_PATH="/wp-admin"';
        $env[] = "TEST_SITE_DB_NAME=\"{$config->acceptance_dbname}\"";
        $env[] = "TEST_SITE_DB_HOST=\"{$config::$mysqlc}\"";
        $env[] = 'TEST_SITE_DB_USER="root"';
        $env[] = 'TEST_SITE_DB_PASSWORD="root"';
        $env[] = 'TEST_SITE_TABLE_PREFIX="wp_"';
        $env[] = "TEST_DB_NAME=\"{$config->integration_dbname}\"";
        $env[] = "TEST_DB_HOST=\"{$config::$mysqlc}\"";
        $env[] = 'TEST_DB_USER="root"';
        $env[] = 'TEST_DB_PASSWORD="root"';
        $env[] = 'TEST_TABLE_PREFIX="wp_"';
        $env[] = "TEST_SITE_WP_URL=\"http://{$config->container}\"";
        $env[] = "TEST_SITE_WP_DOMAIN=\"http://{$config->container}\"";
        $env[] = "TEST_SITE_WP_URL_NO_SCHEME=\"{$config->container}\"";
        $env[] = 'TEST_SITE_ADMIN_EMAIL="admin@example.com"';
        $env[] = 'TEST_SITE_ADMIN_USERNAME="root"';
        $env[] = 'TEST_SITE_ADMIN_PASSWORD="root"';
        $env[] = "SELENOID_HOST=\"{$config::$selenoidc}\"";
        $env[] = "SELENOID_PORT={$config::$selenoidPort}";

        $this->filePutContents("{$workingdir}/.env.testing", join("\n", $env));
    }

    /**
     * Generates `Helper` module files for each suite, if required
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function generateHelpers() {
        if (!$this->withHelpers) {
            return;
        }
        $this->logHeader('Generating Helper files');

        $this->generateHelper('Unit');
        $this->generateHelper('Wpunit');
        $this->generateHelper('Acceptance');
        $this->generateHelper('Functional');
        if ($this->client->getConfig()->useSelenoid) {
            $this->generateHelper('Selenium');
        }
    }

    /**
     * Generates sample test files for each suite, if required
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function generateSampleTests() {
        if (!$this->withSampleTests) {
            return;
        }
        $this->logHeader('Creating sample tests for each suite');

        $this->client->codeceptFromHost('g:test unit Sample');
        $this->client->codeceptFromHost('g:wpunit wpunit Sample');
        $this->client->codeceptFromHost('g:cest acceptance Sample');
        $this->client->codeceptFromHost('g:cest functional Sample');
        if ($this->client->getConfig()->useSelenoid) {
            $this->client->codeceptFromHost('g:cest selenium-bridge Sample');
            $this->client->codeceptFromHost('g:cest selenium-localhost Sample');
        }
    }

    /**
     * Sets up Selenoid for Selenium WebDriver tests
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function setupSelenoid() {
        if (!$this->client->getConfig()->useSelenoid) {
            return;
        }
        $this->logHeader('Setting up Selenoid for Selenium WebDriver tests');

        $workingdir = Client::getAbsPath();
        $this->copyFileToCwd("{$this::$vendordir}/conf/selenium-bridge.suite.yml", "{$workingdir}/tests/selenium-bridge.suite.yml");
        $this->copyFileToCwd("{$this::$vendordir}/conf/selenium-localhost.suite.yml", "{$workingdir}/tests/selenium-localhost.suite.yml");
        $this->copyFileToCwd("{$this::$vendordir}/conf/browsers.json", "{$workingdir}/tests/browsers.json");
        $this->installModule('codeception/module-webdriver', ':^1.0');
    }

    /**
     * Copies YAML suite config files to project directory
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createConfigFiles() {
        $this->logHeader('Creating config files');

        $workingdir = Client::getAbsPath();
        $this->createEnvTestingFile();
        $this->copyFileToCwd("{$this::$vendordir}/conf/codeception.yml", "{$workingdir}/codeception.yml");
        $this->copyFileToCwd("{$this::$vendordir}/conf/acceptance.suite.yml", "{$workingdir}/tests/acceptance.suite.yml");
        $this->copyFileToCwd("{$this::$vendordir}/conf/functional.suite.yml", "{$workingdir}/tests/functional.suite.yml");
        $this->copyFileToCwd("{$this::$vendordir}/conf/wpunit.suite.yml", "{$workingdir}/tests/wpunit.suite.yml");
        $this->copyFileToCwd("{$this::$vendordir}/conf/unit.suite.yml", "{$workingdir}/tests/unit.suite.yml");
    }

    /**
     * Generates a `Helper` file
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $name
     * @return void
     */
    public function generateHelper($name) {
        Logger::info('Creating Helper ' . Logger::yellow($name));

        $workingdir = Client::getAbsPath();
        if (!file_exists("{$workingdir}/tests/_support/Helper/{$name}.php")) {
            $this->client->codeceptFromHost("g:helper {$name}");
            return;
        }

        Logger::warn('Already exists. Skipping.');
    }

    /**
     * Installs a given module if it is not already installed
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $package
     * @param string $vconstraint
     * @return void
     */
    public function installModule($package, $vconstraint = '') {
        exec("composer show {$package} 2>/dev/null", $out, $code);
        if ($code === 1) {
            Logger::info('Installing ' . Logger::yellow($package) . '...');
            passthru("composer require {$package}{$vconstraint} --dev -q");
            return;
        }

        Logger::warn(Logger::yellow($package) . ' is already installed. Skipping.');
    }

    /**
     * Logs bootstrap step header
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $text
     * @param bool   $newline
     * @return void
     */
    public function logHeader($text, $newline = true) {
        if ($newline) {
            print "\n";
        }
        Logger::info("{$this::$stepheader} {$text} {$this::$stepheader}");
    }

    /**
     * Creates a directory if it doesn't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $target
     * @return void
     */
    public function mkdir($target) {
        Logger::info('Creating directory ' . Logger::yellow($target));
        if (!is_dir($target)) {
            $res = @mkdir($target, 0755, true);
            if ($res === false) {
                Logger::error('Failed creating directory');
            } else {
                Logger::info(Logger::green('Success'));
            }
            return;
        }

        Logger::warn('Already exists. Skipping.');
    }

    /**
     * Creates a file if it doesn't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $absdest
     * @param string $contents
     * @return void
     */
    public function filePutContents($absdest, $contents) {
        Logger::info('Creating file ' . Logger::yellow($absdest));
        if (!file_exists($absdest)) {
            $res = @file_put_contents($absdest, $contents);
            if ($res === false) {
                Logger::error('Failed creating file');
            } else {
                Logger::info(Logger::green('Success'));
            }
            return;
        }

        Logger::warn('Already exists. Skipping.');
    }

    /**
     * Copies file at the specified source to an absolute path if doesn't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $src
     * @param string $absdest
     * @return void
     */
    public function copyFileToCwd($src, $absdest) {
        Logger::info('Copying ' . Logger::yellow($src) . ' to ' . Logger::yellow($absdest));
        if (!file_exists($absdest)) {
            $res = @copy($src, $absdest);
            if ($res === false) {
                Logger::error('Failed copying');
            } else {
                Logger::info(Logger::green('Success'));
            }
            return;
        }

        Logger::warn('Already exists. Skipping.');
    }
}
