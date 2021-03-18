<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\Config;

/**
 * Init command
 */
class Init
{
    /**
     * Dependency injected config model
     *
     * @var Client
     */
    private $client;

    /**
     * Object that holds methods for environment creation/initialization
     *
     * @var Up
     */
    private $runner;

    /**
     * Injects runner
     *
     * @param Up $runner
     */
    public function __construct(Up $runner) {
        $this->runner = $runner;
        $this->client = $this->runner->client;
    }

    /**
     * Initializes Docker containers and creates Codeception scaffolding
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        $this->runner->createEnvironments();
        $this->generateScaffolding();
        passthru('composer dump-autoload --optimize');
    }

    /**
     * Copies test scaffolding folders and files to project dir if they don't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function generateScaffolding() {
        $env = [];
        $env[] = 'WP_ROOT_FOLDER="' . Config::WPROOT . '"';
        $env[] = 'TEST_SITE_WP_ADMIN_PATH="/wp-admin"';
        $env[] = 'TEST_SITE_DB_NAME="' . $this->client->getConfig()->dockermeta['acceptance']['dbname'] . '"';
        $env[] = 'TEST_SITE_DB_HOST="' . $this->client->getConfig()->dockermeta['acceptance']['containers']['db'] . '"';
        $env[] = 'TEST_SITE_DB_USER="root"';
        $env[] = 'TEST_SITE_DB_PASSWORD="root"';
        $env[] = 'TEST_SITE_TABLE_PREFIX="wp_"';
        $env[] = 'TEST_DB_NAME="' . $this->client->getConfig()->dockermeta['integration']['dbname'] . '"';
        $env[] = 'TEST_DB_HOST="' . $this->client->getConfig()->dockermeta['integration']['containers']['db'] . '"';
        $env[] = 'TEST_DB_USER="root"';
        $env[] = 'TEST_DB_PASSWORD="root"';
        $env[] = 'TEST_TABLE_PREFIX="wp_"';
        $env[] = 'TEST_SITE_WP_URL="http://' . $this->client->getConfig()->dockermeta['acceptance']['containers']['wordpress'] . '"';
        $env[] = 'TEST_SITE_WP_DOMAIN="http://' . $this->client->getConfig()->dockermeta['integration']['containers']['wordpress'] . '"';
        $env[] = 'TEST_SITE_ADMIN_EMAIL="admin@example.com"';
        $env[] = 'TEST_SITE_ADMIN_USERNAME="root"';
        $env[] = 'TEST_SITE_ADMIN_PASSWORD="root"';
        @file_put_contents(
            Client::getAbsPath() . '/.env.testing',
            join("\n", $env)
        );

        $vendordir = './vendor/aivec/codecept-docker';
        $gitignore = "*\n";
        $gitignore .= '!.gitignore';

        if (!file_exists(Client::getAbsPath() . '/codeception.yml')) {
            $this->client->dockerExec('cp ' . $vendordir . '/conf/codeception.yml codeception.yml');
        }
        if (!file_exists(Client::getAbsPath() . '/tests/acceptance.suite.yml')) {
            $this->client->dockerExec('cp ' . $vendordir . '/conf/acceptance.suite.yml tests/acceptance.suite.yml');
        }
        if (!file_exists(Client::getAbsPath() . '/tests/functional.suite.yml')) {
            $this->client->dockerExec('cp ' . $vendordir . '/conf/functional.suite.yml tests/functional.suite.yml');
        }
        if (!file_exists(Client::getAbsPath() . '/tests/unit.suite.yml')) {
            $this->client->dockerExec('cp ' . $vendordir . '/conf/unit.suite.yml tests/unit.suite.yml');
        }
        if (!file_exists(Client::getAbsPath() . '/tests/wpunit.suite.yml')) {
            $this->client->dockerExec('cp ' . $vendordir . '/conf/wpunit.suite.yml tests/wpunit.suite.yml');
        }

        if (!is_dir(Client::getAbsPath() . '/tests/_support')) {
            $this->client->codecept('g:helper Unit');
            $this->client->codecept('g:helper Wpunit');
            $this->client->codecept('g:helper Acceptance');
            $this->client->codecept('g:helper Functional');
            $this->client->codecept('build');
            @file_put_contents(
                Client::getAbsPath() . '/tests/_support/_generated/.gitignore',
                $gitignore
            );
        }

        if (!is_dir(Client::getAbsPath() . '/tests/_data')) {
            $this->client->dockerExec('mkdir -p tests/_data');
            $this->client->dockerExec('touch tests/_data/.gitkeep');
        }
        if (!is_dir(Client::getAbsPath() . '/tests/_output')) {
            $this->client->dockerExec('mkdir -p tests/_output');
            $this->client->dockerExec('touch tests/_output/.gitkeep');
            @file_put_contents(
                Client::getAbsPath() . '/tests/_output/.gitignore',
                $gitignore
            );
        }

        // generate sample tests
        $this->client->codecept('g:wpunit wpunit Sample');
        $this->client->codecept('g:test unit Sample');
    }
}
