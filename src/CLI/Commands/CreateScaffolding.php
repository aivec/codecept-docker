<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\CLI\Runner;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;

/**
 * CreateScaffolding command
 */
class CreateScaffolding implements Runner
{
    /**
     * Client object
     *
     * @var Client
     */
    public $client;

    /**
     * Initializes command
     *
     * @param array $conf
     */
    public function __construct(array $conf) {
        $this->client = new Client(new Config($conf));
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
            $this->createScaffolding();
        } catch (InvalidConfigException $e) {
            (new Logger())->configError($e);
            exit(1);
        }
    }

    /**
     * Copies test scaffolding folders and files to project dir if they don't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createScaffolding() {
        $workingdir = Client::getAbsPath();
        $config = $this->client->getConfig();

        // create .env.testing file
        $env = [];
        $env[] = 'WP_ROOT_FOLDER="' . Config::WPROOT . '"';
        $env[] = 'TEST_SITE_WP_ADMIN_PATH="/wp-admin"';
        $env[] = 'TEST_SITE_DB_NAME="' . $config->dockermeta['acceptance']['dbname'] . '"';
        $env[] = 'TEST_SITE_DB_HOST="' . $config->dockermeta['acceptance']['containers']['db'] . '"';
        $env[] = 'TEST_SITE_DB_USER="root"';
        $env[] = 'TEST_SITE_DB_PASSWORD="root"';
        $env[] = 'TEST_SITE_TABLE_PREFIX="wp_"';
        $env[] = 'TEST_DB_NAME="' . $config->dockermeta['integration']['dbname'] . '"';
        $env[] = 'TEST_DB_HOST="' . $config->dockermeta['integration']['containers']['db'] . '"';
        $env[] = 'TEST_DB_USER="root"';
        $env[] = 'TEST_DB_PASSWORD="root"';
        $env[] = 'TEST_TABLE_PREFIX="wp_"';
        $env[] = 'TEST_SITE_WP_URL="http://' . $config->dockermeta['acceptance']['containers']['wordpress'] . '"';
        $env[] = 'TEST_SITE_WP_DOMAIN="http://' . $config->dockermeta['integration']['containers']['wordpress'] . '"';
        $env[] = 'TEST_SITE_ADMIN_EMAIL="admin@example.com"';
        $env[] = 'TEST_SITE_ADMIN_USERNAME="root"';
        $env[] = 'TEST_SITE_ADMIN_PASSWORD="root"';
        $env[] = 'SELENOID_HOST="' . $config->namespace . '_selenoid"';
        $env[] = 'SELENOID_PORT=' . $config->selenoidPort;
        if (!file_exists("{$workingdir}/.env.testing")) {
            file_put_contents("{$workingdir}/.env.testing", join("\n", $env));
        }

        $vendordir = './vendor/aivec/codecept-docker';
        $gitignore = "*\n";
        $gitignore .= '!.gitignore';

        // install necessary modules
        exec('composer show codeception/module-db 2>/dev/null', $out, $code);
        if ($code === 1) {
            passthru('composer require codeception/module-db --dev');
        }
        exec('composer show codeception/module-phpbrowser 2>/dev/null', $out, $code);
        if ($code === 1) {
            passthru('composer require codeception/module-phpbrowser --dev');
        }
        exec('composer show codeception/module-cli 2>/dev/null', $out, $code);
        if ($code === 1) {
            passthru('composer require codeception/module-cli --dev');
        }
        exec('composer show codeception/module-asserts 2>/dev/null', $out, $code);
        if ($code === 1) {
            passthru('composer require codeception/module-asserts --dev');
        }

        // add codeception test scaffolding
        if (!is_dir("{$workingdir}/tests")) {
            $this->client->dockerExec('mkdir -p tests');
        }
        if (!is_dir("{$workingdir}/tests/_support")) {
            $this->client->dockerExec('mkdir -p tests/_support');
        }
        if (!is_dir("{$workingdir}/tests/_support/_generated")) {
            $this->client->dockerExec('mkdir -p tests/_support/_generated');
        }
        if (!file_exists("{$workingdir}/tests/_support/_generated/.gitignore")) {
            file_put_contents("{$workingdir}/tests/_support/_generated/.gitignore", $gitignore);
        }
        if (!is_dir("{$workingdir}/tests/_data")) {
            $this->client->dockerExec('mkdir -p tests/_data');
        }
        $this->client->dockerExec('touch tests/_data/.gitkeep');
        if (!is_dir("{$workingdir}/tests/_output")) {
            $this->client->dockerExec('mkdir -p tests/_output');
        }
        $this->client->dockerExec('touch tests/_output/.gitkeep');
        if (!file_exists("{$workingdir}/tests/_output/.gitignore")) {
            file_put_contents("{$workingdir}/tests/_output/.gitignore", $gitignore);
        }

        // add suite YAML configs
        if (!file_exists("{$workingdir}/codeception.yml")) {
            $this->client->dockerExec("cp {$vendordir}/conf/codeception.yml codeception.yml");
        }
        if (!file_exists("{$workingdir}/tests/acceptance.suite.yml")) {
            $this->client->dockerExec("cp {$vendordir}/conf/acceptance.suite.yml tests/acceptance.suite.yml");
        }
        if (!file_exists("{$workingdir}/tests/functional.suite.yml")) {
            $this->client->dockerExec("cp {$vendordir}/conf/functional.suite.yml tests/functional.suite.yml");
        }
        if (!file_exists("{$workingdir}/tests/unit.suite.yml")) {
            $this->client->dockerExec("cp {$vendordir}/conf/unit.suite.yml tests/unit.suite.yml");
        }
        if (!file_exists("{$workingdir}/tests/wpunit.suite.yml")) {
            $this->client->dockerExec("cp {$vendordir}/conf/wpunit.suite.yml tests/wpunit.suite.yml");
        }

        // setup selenoid if applicable
        if ($config->useSelenoid) {
            if (!file_exists("{$workingdir}/tests/selenoid.suite.yml")) {
                $this->client->dockerExec("cp {$vendordir}/conf/selenoid.suite.yml tests/selenoid.suite.yml");
            }
            if (!file_exists("{$workingdir}/tests/browsers.json")) {
                $this->client->dockerExec("cp {$vendordir}/conf/browsers.json tests/browsers.json");
            }

            exec('composer show codeception/module-webdriver 2>/dev/null', $out, $code);
            if ($code === 1) {
                passthru('composer require codeception/module-webdriver --dev');
            }
        }

        // generate helpers if necessary
        if (!file_exists("{$workingdir}/tests/_support/Helper/Unit.php")) {
            $this->client->codecept('g:helper Unit');
        }
        if (!file_exists("{$workingdir}/tests/_support/Helper/Wpunit.php")) {
            $this->client->codecept('g:helper Wpunit');
        }
        if (!file_exists("{$workingdir}/tests/_support/Helper/Acceptance.php")) {
            $this->client->codecept('g:helper Acceptance');
        }
        if (!file_exists("{$workingdir}/tests/_support/Helper/Functional.php")) {
            $this->client->codecept('g:helper Functional');
        }

        $this->client->codecept('build');
    }
}
