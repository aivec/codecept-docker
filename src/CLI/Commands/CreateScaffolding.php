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
        $config = $this->client->getConfig();
        $workingdir = Client::getAbsPath();
        $wproot = Config::WPROOT;

        // create .env.testing file
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
            mkdir('tests', 0755, true);
        }
        if (!is_dir("{$workingdir}/tests/_support")) {
            mkdir('tests/_support', 0755, true);
        }
        if (!is_dir("{$workingdir}/tests/_support/_generated")) {
            mkdir('tests/_support/_generated', 0755, true);
        }
        if (!file_exists("{$workingdir}/tests/_support/_generated/.gitignore")) {
            file_put_contents("{$workingdir}/tests/_support/_generated/.gitignore", $gitignore);
        }
        if (!is_dir("{$workingdir}/tests/_data")) {
            mkdir('tests/_data', 0755, true);
        }
        file_put_contents('tests/_data/.gitkeep', '');
        if (!is_dir("{$workingdir}/tests/_output")) {
            mkdir('tests/_output', 0755, true);
        }
        file_put_contents('tests/_output/.gitkeep', '');
        if (!file_exists("{$workingdir}/tests/_output/.gitignore")) {
            file_put_contents("{$workingdir}/tests/_output/.gitignore", $gitignore);
        }

        // add suite YAML configs
        if (!file_exists("{$workingdir}/codeception.yml")) {
            copy("{$vendordir}/conf/codeception.yml", 'codeception.yml');
        }
        if (!file_exists("{$workingdir}/tests/acceptance.suite.yml")) {
            copy("{$vendordir}/conf/acceptance.suite.yml", 'tests/acceptance.suite.yml');
        }
        if (!file_exists("{$workingdir}/tests/functional.suite.yml")) {
            copy("{$vendordir}/conf/functional.suite.yml", 'tests/functional.suite.yml');
        }
        if (!file_exists("{$workingdir}/tests/unit.suite.yml")) {
            copy("{$vendordir}/conf/unit.suite.yml", 'tests/unit.suite.yml');
        }
        if (!file_exists("{$workingdir}/tests/wpunit.suite.yml")) {
            copy("{$vendordir}/conf/wpunit.suite.yml", 'tests/wpunit.suite.yml');
        }

        // setup selenoid if applicable
        if ($config->useSelenoid) {
            if (!file_exists("{$workingdir}/tests/selenium-bridge.suite.yml")) {
                copy("{$vendordir}/conf/selenium-bridge.suite.yml", 'tests/selenium-bridge.suite.yml');
            }
            if (!file_exists("{$workingdir}/tests/selenium-localhost.suite.yml")) {
                copy("{$vendordir}/conf/selenium-localhost.suite.yml", 'tests/selenium-localhost.suite.yml');
            }
            if (!file_exists("{$workingdir}/tests/browsers.json")) {
                copy("cp {$vendordir}/conf/browsers.json", 'tests/browsers.json');
            }

            exec('composer show codeception/module-webdriver 2>/dev/null', $out, $code);
            if ($code === 1) {
                passthru('composer require codeception/module-webdriver --dev');
            }
        }

        // generate helpers if necessary
        if (!file_exists("{$workingdir}/tests/_support/Helper/Unit.php")) {
            $this->client->codeceptFromHost('g:helper Unit');
        }
        if (!file_exists("{$workingdir}/tests/_support/Helper/Wpunit.php")) {
            $this->client->codeceptFromHost('g:helper Wpunit');
        }
        if (!file_exists("{$workingdir}/tests/_support/Helper/Acceptance.php")) {
            $this->client->codeceptFromHost('g:helper Acceptance');
        }
        if (!file_exists("{$workingdir}/tests/_support/Helper/Functional.php")) {
            $this->client->codeceptFromHost('g:helper Functional');
        }

        $this->client->codeceptFromHost('build');
    }
}
