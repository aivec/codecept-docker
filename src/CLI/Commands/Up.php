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
        $workingdirname = Client::getWorkingDirname();
        $vendordir = $workingdir . Config::VENDORDIR;
        $wproot = Config::WPROOT;
        $volumes = [];
        $volume = '';
        @mkdir("{$workingdir}/tests", 0755);
        switch ($conf->projectType) {
            case 'other':
                $pluginfile = "{$workingdir}/tests/implementation-plugin/implementation-plugin.php";
                if (!file_exists($pluginfile)) {
                    mkdir("{$workingdir}/tests/implementation-plugin", 0755);
                    copy("{$vendordir}/implementation-plugin.php", $pluginfile);
                }
                $volumes[] = "-v {$workingdir}/tests/implementation-plugin:{$wproot}/wp-content/plugins/implementation-plugin";
                $volumes[] = "-v {$workingdir}:{$wproot}/wp-content/plugins/{$workingdirname}";
                break;
            case 'plugin':
                $volumes[] = "-v {$workingdir}:{$wproot}/wp-content/plugins/{$workingdirname}";
                break;
            case 'theme':
                $volumes[] = "-v {$workingdir}:{$wproot}/wp-content/themes/{$workingdirname}";
                break;
        }

        if (!empty($conf->ssh)) {
            foreach ($conf->ssh as $sshc) {
                $sshvpath = Config::AVC_SSH_DIR;
                $realsshpath = realpath($sshc['privateKeyPath']);
                $sshfname = $sshc['privateKeyFilename'];
                $volumes[] = "-v {$realsshpath}:{$sshvpath}/{$sshfname}";
            }
        }

        // mounting a script here tells the visiblevc run.sh script to run it before starting apache
        $volumes[] = "-v {$vendordir}/src/scripts/initwp.sh:/docker-entrypoint-initwp.d/initwp.sh";
        $volume = join(' ', $volumes);

        // build WordPress docker image
        $wpimage = "wpcodecept:latest-{$conf->phpVersion}";
        if (!empty($conf->imagePath)) {
            $pieces = explode('/', $conf->imagePath);
            $fnamepieces = explode('.tar', $pieces[count($pieces) - 1]);
            $wpimage = $fnamepieces[0];
            passthru("docker load -i {$conf->imagePath}");
        } else {
            passthru("docker build -t {$wpimage} -f {$vendordir}/docker/Dockerfile.php{$conf->phpVersion} {$vendordir}");
        }

        // create CodeceptDocker network for WordPress and MySQL container
        passthru("docker network create --attachable {$conf::$network}");

        // create volume for database data
        passthru("docker volume create {$conf::$mysqldbv}");

        // create and run mysql container
        passthru("docker run -d --name {$conf::$mysqlc} \
            --network {$conf::$network} \
            --env MYSQL_USER=admin \
            --env MYSQL_PASSWORD=admin \
            --env MYSQL_ROOT_PASSWORD=root \
            -v {$conf::$mysqldbv}:/var/lib/mysql \
            mysql:5.7");

        // create and run phpmyadmin container
        passthru("docker run -d --name {$conf::$phpmyadminc} \
            --network {$conf::$network} \
            -p 33333:80 \
            -e PMA_HOST={$conf::$mysqlc} \
            -e MYSQL_ROOT_PASSWORD=root \
            phpmyadmin/phpmyadmin");

        $envvars = $conf->envvars;
        $envvars['AVC_META_DIR'] = Config::AVC_META_DIR;
        $envvars['AVC_SCRIPTS_DIR'] = Config::AVC_SCRIPTS_DIR;
        $envvars['AVC_DUMPFILES_DIR'] = Config::AVC_DUMPFILES_DIR;
        $envvars['AVC_SSH_DIR'] = Config::AVC_SSH_DIR;
        $envvars['AVC_USER_SCRIPTS_DIR'] = Config::AVC_USER_SCRIPTS_DIR;
        $envvars['AVC_TEMP_DIR'] = Config::AVC_TEMP_DIR;
        $envvars['AVC_CACHE_DIR'] = Config::AVC_CACHE_DIR;
        $envvars['ACCEPTANCE_DB_NAME'] = $conf->acceptance_dbname;
        $envvars['INTEGRATION_DB_NAME'] = $conf->integration_dbname;
        $envvars['RUNNING_FROM_CACHE'] = (int)!empty($conf->imagePath);
        $envvars['DOCKER_BRIDGE_IP'] = 'host.docker.internal';
        $envvars['FTP_CONFIGS'] = trim(json_encode(json_encode($conf->ftp, JSON_UNESCAPED_SLASHES), JSON_UNESCAPED_SLASHES));
        $envvars['SSH_CONFIGS'] = trim(json_encode(json_encode($conf->ssh, JSON_UNESCAPED_SLASHES), JSON_UNESCAPED_SLASHES));
        $envvars['DOWNLOAD_PLUGINS'] = trim(json_encode(json_encode(array_merge(
            $conf->downloadPlugins,
            ['relative-url']
        ), JSON_UNESCAPED_SLASHES), JSON_UNESCAPED_SLASHES));
        $envvars['DOWNLOAD_THEMES'] = trim(json_encode(json_encode($conf->downloadThemes, JSON_UNESCAPED_SLASHES), JSON_UNESCAPED_SLASHES));
        $envvars['PLUGINS'] = join(' ', $conf->downloadPlugins);
        if (!empty($conf->downloadThemes)) {
            $envvars['THEMES'] = join(' ', $conf->downloadThemes);
        }

        // set default values for various WP envvars
        $envvars['DB_NAME'] = $conf->acceptance_dbname; // DB_NAME is updated before tests are run
        $envvars['DB_HOST'] = $conf::$mysqlc;
        $envvars['DB_PASS'] = 'root';
        $envvars['WP_DEBUG'] = 'true';
        $envvars['WP_DEBUG_DISPLAY'] = 'true';
        $envvars['WP_DEBUG_LOG'] = 'true';
        $envvars['WP_LOCALE'] = $conf->language;
        $envvars['WP_VERSION'] = $conf->wordpressVersion;

        if (!empty($conf->envvars)) {
            // override defaults if they are set in env object
            foreach ($conf->envvars as $key => $value) {
                if (in_array($key, ['WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG', 'MULTISITE'], true)) {
                    if ($value === true || $value === 1 || $value === 'true') {
                        $envvars[$key] = 'true';
                    } elseif ($value === false || $value === 0 || $value === 'false') {
                        $envvars[$key] = 'false';
                    }
                }
            }
        }

        // used for accessing constants from PHP $_ENV global
        $envvars['APACHE_ENV_VARS'] = trim(json_encode(json_encode($envvars)));

        $envarsstrings = '--env ' . join(' --env ', array_map(function ($key, $value) {
            return $key . '=' . $value;
        }, array_keys($envvars), $envvars));

        // create and run WordPress containers
        passthru("docker run -d --name {$conf->container} \
            --cap-add=SYS_ADMIN \
            --device=/dev/fuse \
            --security-opt apparmor=unconfined \
            --add-host=host.docker.internal:host-gateway  \
            --network {$conf::$network} \
            {$envarsstrings} \
            {$volume} \
            {$wpimage}");

        if ($conf->useSelenoid) {
            passthru("docker run -d --name {$conf::$selenoidc} \
                --network {$conf::$network} \
                --expose {$conf::$selenoidPort} \
                -v /var/run/docker.sock:/var/run/docker.sock \
                -v {$workingdir}/tests/_output/video/:/opt/selenoid/video/ \
                -v {$workingdir}/tests/_output/logs/:/opt/selenoid/logs/ \
                -v {$workingdir}/tests/browsers.json:/etc/selenoid/browsers.json:ro \
                -e OVERRIDE_VIDEO_OUTPUT_DIR={$workingdir}/tests/_output/video/ \
                aerokube/selenoid:1.10.3 -container-network {$conf::$network} \
                -log-output-dir /opt/selenoid/logs");
        }

        passthru("docker logs -f {$conf->container}");
    }
}
