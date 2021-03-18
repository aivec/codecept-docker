<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\Config;

/**
 * Command for creating and setting up Docker images/containers.
 */
class Up
{
    /**
     * Dependency injected config model
     *
     * @var Client
     */
    public $client;

    /**
     * Initializes config member var
     *
     * @param Client $client
     */
    public function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * Spins up docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createEnvironments(): void {
        $volumes = [];
        $volume = '';
        @mkdir(Client::getAbsPath() . '/tests', 0755);
        switch ($this->client->getConfig()->projectType) {
            case 'library':
                $pluginfile = Client::getAbsPath() . '/tests/implementation-plugin/implementation-plugin.php';
                if (!file_exists($pluginfile)) {
                    @mkdir(Client::getAbsPath() . '/tests/implementation-plugin', 0755);
                    @copy(
                        Client::getAbsPath() . Config::VENDORDIR . '/implementation-plugin.php',
                        $pluginfile
                    );
                }
                $volumes[] = '-v ' . Client::getAbsPath() . '/tests/implementation-plugin' . ':' . Config::WPROOT . '/wp-content/plugins/implementation-plugin';
                $volumes[] = '-v ' . Client::getAbsPath() . ':' . Config::WPROOT . '/wp-content/plugins/' . Client::getWorkingDirname();
                break;
            case 'plugin':
                $volumes[] = '-v ' . Client::getAbsPath() . ':' . Config::WPROOT . '/wp-content/plugins/' . Client::getWorkingDirname();
                break;
            case 'theme':
                $volumes[] = '-v ' . Client::getAbsPath() . ':' . Config::WPROOT . '/wp-content/themes/' . Client::getWorkingDirname();
                break;
        }

        $volumes[] = '-v ' . Client::getAbsPath() . Config::VENDORDIR . '/install_plugins_themes.sh:' . Config::EXTRASDIR . '/install_plugins_themes.sh';
        if (!empty($this->client->getConfig()->ssh)) {
            foreach ($this->client->getConfig()->ssh as $sshc) {
                $sshvpath = Config::EXTRASDIR . '/ssh';
                $volumes[] = '-v ' . realpath($sshc['privateKeyPath']) . ':' . $sshvpath . '/' . $sshc['privateKeyFilename'];
            }
        }

        $volume = join(' ', $volumes);

        // build docker image
        passthru('cd ' . Client::getAbsPath() . Config::VENDORDIR . ' && docker build --build-arg WP_VERSION=' . $this->client->getConfig()->wordpressVersion . ' . -t wpcodecept');

        // create CodeceptDocker network for wordpress-apache container and mysql container
        passthru('docker network create --attachable ' . $this->client->getConfig()->network);

        $res = [];
        exec("docker network inspect bridge -f '{{ (index .IPAM.Config 0).Gateway }}'", $res);
        $bridgeip = !empty($res[0]) ? $res[0] : '\'\'';

        foreach ($this->client->getConfig()->dockermeta as $type => $info) {
            // create and run mysql container
            passthru('docker run -d --name ' . $info['containers']['db'] . ' \
                --network ' . $this->client->getConfig()->network . ' \
                --env MYSQL_DATABASE=' . $info['dbname'] . ' \
                --env MYSQL_USER=admin \
                --env MYSQL_PASSWORD=admin \
                --env MYSQL_ROOT_PASSWORD=root \
                mysql:5.7');

            $envvars = [
                'WORDPRESS_DB_HOST' => $info['containers']['db'],
                'WORDPRESS_DB_USER' => 'root',
                'WORDPRESS_DB_PASSWORD' => 'root',
                'WORDPRESS_DB_NAME' => $info['dbname'],
                'XDEBUG_PORT' => $info['xdebugport'],
                'FTP_CONFIGS' => '\'' . json_encode($this->client->getConfig()->ftp) . '\'',
                'SSH_CONFIGS' => '\'' . json_encode($this->client->getConfig()->ssh) . '\'',
                'LANG' => $this->client->getConfig()->lang,
                'DOCKER_BRIDGE_IP' => $bridgeip,
            ];

            $envarsstrings = '--env ' . join(' --env ', array_map(function ($key, $value) {
                return $key . '=' . $value;
            }, array_keys($envvars), $envvars));

            // create and run WordPress containers
            passthru('docker run -d --name ' . $info['containers']['wordpress'] . ' \
                --network ' . $this->client->getConfig()->network . ' \
                ' . $envarsstrings . ' \
                --env APACHE_ENV_VARS=' . json_encode(json_encode($envvars)) . ' \
                ' . $volume . ' wpcodecept');

            // change ownership of wp-content and plugins/themes directories to www-data:www-data so
            // WP-CLI doesn't fail
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content');
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content/plugins');
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content/themes');
        }

        print("Waiting for MySQL containers to be ready...\n");
        foreach ($this->client->getConfig()->dockermeta as $type => $info) {
            $sleep = [];
            $sleep[] = 'docker exec -i --user 1000:1000 ' . $info['containers']['wordpress'] . ' /bin/bash -c';
            $sleep[] = '\'maxretries=5;';
            $sleep[] = 'retries=0;';
            $sleep[] = 'while ! mysqladmin ping -h"' . $info['containers']['db'] . '" --silent; do';
            $sleep[] = 'if [ "$retries" -gt "$maxretries" ]; then';
            $sleep[] = 'echo "Unable to connect to MySQL database. Aborting.";';
            $sleep[] = 'exit 1;';
            $sleep[] = 'fi;';
            $sleep[] = 'sleep 3;';
            $sleep[] = 'retries=$(($retries + 1));';
            $sleep[] = 'done\'';
        }

        passthru(join(' ', $sleep));

        // install WordPress core
        print("Installing WordPress...\n");
        $this->client->wpAcceptanceCLI('core install \
            --url=' . $this->client->getConfig()->dockermeta['acceptance']['containers']['wordpress'] . ' \
            --title=Tests \
            --admin_user=root --admin_password=root \
            --admin_email=admin@example.com');
        $this->client->wpIntegrationCLI('core install \
            --url=' . $this->client->getConfig()->dockermeta['integration']['containers']['wordpress'] . ' \
            --title=Tests \
            --admin_user=root --admin_password=root \
            --admin_email=admin@example.com');

        $this->installAndActivateLanguage();
        $this->installAndActivatePlugins();
        $this->installThemes();
        $this->installPrivatePluginsAndThemes();
    }

    /**
     * Installs and activates language defined in `codecept-docker.json` config file
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installAndActivateLanguage(): void {
        $this->client->wpAcceptanceCLI('language core install ' . $this->client->getConfig()->lang);
        $this->client->wpAcceptanceCLI('site switch-language ' . $this->client->getConfig()->lang);
        $this->client->wpIntegrationCLI('language core install ' . $this->client->getConfig()->lang);
        $this->client->wpIntegrationCLI('site switch-language ' . $this->client->getConfig()->lang);
    }

    /**
     * Installs and activates plugins defined in `codecept-docker.json` config file
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installAndActivatePlugins(): void {
        foreach ($this->client->getConfig()->downloadPlugins as $plugin) {
            $this->client->wpAcceptanceCLI('plugin install ' . $plugin);
            $this->client->wpAcceptanceCLI('plugin activate ' . $plugin);
            $this->client->wpIntegrationCLI('plugin install ' . $plugin);
            $this->client->wpIntegrationCLI('plugin activate ' . $plugin);
        }
    }

    /**
     * Installs themes defined in `codecept-docker.json` config file
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installThemes(): void {
        foreach ($this->client->getConfig()->downloadThemes as $theme) {
            $this->client->wpAcceptanceCLI('theme install ' . $theme);
            $this->client->wpIntegrationCLI('theme install ' . $theme);
        }
    }

    /**
     * Installs plugins/themes downloaded via SCP after the container has been created
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installPrivatePluginsAndThemes(): void {
        foreach ($this->client->getConfig()->dockermeta as $type => $info) {
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' ./extras/install_plugins_themes.sh');
        }
        foreach ((array)$this->client->getConfig()->ssh as $sshc) {
            if (!empty($sshc['plugins'])) {
                foreach ((array)$sshc['plugins'] as $pluginpath) {
                    if ((string)substr($pluginpath, -4) !== '.zip') {
                        $pluginpath .= '.zip';
                    }
                    $plugin = basename($pluginpath);
                    $this->client->wpAcceptanceCLI('plugin install ' . Config::EXTRASDIR . '/ssh/plugins/' . $plugin);
                    $this->client->wpIntegrationCLI('plugin install ' . Config::EXTRASDIR . '/ssh/plugins/' . $plugin);
                }
            }
            if (!empty($sshc['themes'])) {
                foreach ((array)$sshc['themes'] as $themepath) {
                    if ((string)substr($themepath, -4) !== '.zip') {
                        $themepath .= '.zip';
                    }
                    $theme = basename($themepath);
                    $this->client->wpAcceptanceCLI('theme install ' . Config::EXTRASDIR . '/ssh/themes/' . $theme);
                    $this->client->wpIntegrationCLI('theme install ' . Config::EXTRASDIR . '/ssh/themes/' . $theme);
                }
            }
        }
    }
}
