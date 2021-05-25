<?php

namespace Aivec\WordPress\CodeceptDocker\CLI\Commands;

use Aivec\WordPress\CodeceptDocker\CLI\Client;
use Aivec\WordPress\CodeceptDocker\CLI\Runner;
use Aivec\WordPress\CodeceptDocker\Config;
use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Aivec\WordPress\CodeceptDocker\Logger;

/**
 * Command for creating and setting up Docker images/containers.
 */
class Up implements Runner
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
            $this->up();
        } catch (InvalidConfigException $e) {
            (new Logger())->configError($e);
            exit(1);
        }
    }

    /**
     * Spins up docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function up(): void {
        $conf = $this->client->getConfig();
        $workingdir = Client::getAbsPath();
        $volumes = [];
        $volume = '';
        @mkdir($workingdir . '/tests', 0755);
        switch ($conf->projectType) {
            case 'library':
                $pluginfile = $workingdir . '/tests/implementation-plugin/implementation-plugin.php';
                if (!file_exists($pluginfile)) {
                    @mkdir($workingdir . '/tests/implementation-plugin', 0755);
                    @copy(
                        $workingdir . Config::VENDORDIR . '/implementation-plugin.php',
                        $pluginfile
                    );
                }
                $volumes[] = '-v ' . $workingdir . '/tests/implementation-plugin' . ':' . Config::WPROOT . '/wp-content/plugins/implementation-plugin';
                $volumes[] = '-v ' . $workingdir . ':' . Config::WPROOT . '/wp-content/plugins/' . Client::getWorkingDirname();
                break;
            case 'plugin':
                $volumes[] = '-v ' . $workingdir . ':' . Config::WPROOT . '/wp-content/plugins/' . Client::getWorkingDirname();
                break;
            case 'theme':
                $volumes[] = '-v ' . $workingdir . ':' . Config::WPROOT . '/wp-content/themes/' . Client::getWorkingDirname();
                break;
        }

        $volumes[] = '-v ' . $workingdir . Config::VENDORDIR . '/install_plugins_themes.sh:' . Config::EXTRASDIR . '/install_plugins_themes.sh';
        if (!empty($conf->ssh)) {
            foreach ($conf->ssh as $sshc) {
                $sshvpath = Config::EXTRASDIR . '/ssh';
                $volumes[] = '-v ' . realpath($sshc['privateKeyPath']) . ':' . $sshvpath . '/' . $sshc['privateKeyFilename'];
            }
        }

        $volume = join(' ', $volumes);

        // build docker image
        passthru('cd ' . $workingdir . Config::VENDORDIR . ' && docker build --build-arg WP_VERSION=' . $conf->wordpressVersion . ' . -t wpcodecept');

        // create CodeceptDocker network for wordpress-apache container and mysql container
        passthru('docker network create --attachable ' . $conf->network);

        $bridgeip = 'host.docker.internal';

        foreach ($conf->dockermeta as $type => $info) {
            // create and run mysql container
            passthru('docker run -d --name ' . $info['containers']['db'] . ' \
                --network ' . $conf->network . ' \
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
                'FTP_CONFIGS' => '\'' . json_encode($conf->ftp) . '\'',
                'SSH_CONFIGS' => '\'' . json_encode($conf->ssh) . '\'',
                'LANG' => $conf->language,
                'DOCKER_BRIDGE_IP' => $bridgeip,
            ];

            $envarsstrings = '--env ' . join(' --env ', array_map(function ($key, $value) {
                return $key . '=' . $value;
            }, array_keys($envvars), $envvars));

            // create and run WordPress containers
            passthru('docker run -d --name ' . $info['containers']['wordpress'] . ' \
                --network ' . $conf->network . ' \
                --add-host=host.docker.internal:host-gateway  \
                ' . $envarsstrings . ' \
                --env APACHE_ENV_VARS=' . json_encode(json_encode($envvars)) . ' \
                ' . $volume . ' wpcodecept');

            // change ownership of wp-content and plugins/themes directories to www-data:www-data so
            // WP-CLI doesn't fail
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content');
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content/plugins');
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content/themes');
        }

        if ($conf->useSelenoid) {
            passthru("docker run -d --name {$conf->namespace}_selenoid \
                --network {$conf->network} \
                --expose {$conf->selenoidPort} \
                -v /var/run/docker.sock:/var/run/docker.sock \
                -v {$workingdir}/tests/_output/video/:/opt/selenoid/video/ \
                -v {$workingdir}/tests/browsers.json:/etc/selenoid/browsers.json:ro \
                -e OVERRIDE_VIDEO_OUTPUT_DIR={$workingdir}/tests/_output/video/ \
                aerokube/selenoid:1.10.3 -container-network {$conf->network}");
        }

        print("Waiting for MySQL containers to be ready...\n");
        foreach ($conf->dockermeta as $type => $info) {
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
            --url=' . $conf->dockermeta['acceptance']['containers']['wordpress'] . ' \
            --title=Tests \
            --admin_user=root --admin_password=root \
            --admin_email=admin@example.com');
        $this->client->wpIntegrationCLI('core install \
            --url=' . $conf->dockermeta['integration']['containers']['wordpress'] . ' \
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
        $this->client->wpAcceptanceCLI('language core install ' . $this->client->getConfig()->language);
        $this->client->wpAcceptanceCLI('site switch-language ' . $this->client->getConfig()->language);
        $this->client->wpIntegrationCLI('language core install ' . $this->client->getConfig()->language);
        $this->client->wpIntegrationCLI('site switch-language ' . $this->client->getConfig()->language);
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
