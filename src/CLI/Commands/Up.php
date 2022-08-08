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
            $this->client->getConfig()->finalize();
            $this->up();
        } catch (InvalidConfigException $e) {
            Logger::configError($e);
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
        $srcdir = '';
        @mkdir("{$workingdir}/tests", 0755);
        switch ($conf->projectType) {
            case 'other':
                $pluginfile = "{$workingdir}/tests/implementation-plugin/implementation-plugin.php";
                if (!file_exists($pluginfile)) {
                    mkdir("{$workingdir}/tests/implementation-plugin", 0755);
                    copy("{$vendordir}/implementation-plugin.php", $pluginfile);
                }
                $srcdir = "{$wproot}/wp-content/plugins/{$workingdirname}";
                $volumes[] = "-v {$workingdir}/tests/implementation-plugin:{$wproot}/wp-content/plugins/implementation-plugin";
                $volumes[] = "-v {$workingdir}:{$srcdir}";
                break;
            case 'plugin':
                $srcdir = "{$wproot}/wp-content/plugins/{$workingdirname}";
                $volumes[] = "-v {$workingdir}:{$srcdir}";
                break;
            case 'theme':
                $srcdir = "{$wproot}/wp-content/themes/{$workingdirname}";
                $volumes[] = "-v {$workingdir}:{$srcdir}";
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
        // mount custom scripts
        $volumes[] = "-v {$vendordir}/src/scripts:/avc-wpdocker-meta/scripts";

        // add customInitScripts if defined
        if (!empty($conf->customInitScripts)) {
            foreach ($conf->customInitScripts as $scriptpath) {
                $absscriptpath = (string)realpath($scriptpath);
                $scriptname = basename($scriptpath);
                $dockerUserScriptsDirpath = Config::AVC_USER_SCRIPTS_DIR;
                $volumes[] = "-v {$absscriptpath}:{$dockerUserScriptsDirpath}/{$scriptname}";
            }
        }

        $volume = join(' ', $volumes);

        // build WordPress docker image
        $wpimage = "wpcodecept:latest-{$conf->phpVersion}";
        if (!empty($conf->imagePath)) {
            exec("docker load -i {$conf->imagePath}", $output);
            $message = explode(':', $output[0]);
            $wpimage = trim($message[1]);
        } else {
            passthru("docker build -t {$wpimage} -f {$vendordir}/docker/Dockerfile.php{$conf->phpVersion} {$vendordir}");
        }

        // build custom aivec/selenoid-video-recorder image
        passthru("docker build -t aivec/selenoid-video-recorder -f {$vendordir}/docker/video-recorder/Dockerfile.video-recorder {$vendordir}/docker/video-recorder");

        // pull selenoid browser images if necessary
        $bfile = "{$workingdir}/tests/browsers.json";
        if ($conf->useSelenoid) {
            if (file_exists($bfile)) {
                $bfilec = file_get_contents($bfile);
                if (!empty($bfilec)) {
                    $bfilejson = json_decode($bfilec, true);
                    foreach ($bfilejson as $bname => $browser) {
                        if (!empty($browser['versions'])) {
                            Logger::info("Pulling selenoid browser ({$bname}) images for Selenium WebDriver suites...");
                            foreach ($browser['versions'] as $version => $config) {
                                if (!empty($config['image'])) {
                                    $bimage = $config['image'];
                                    passthru("docker image pull {$bimage}");
                                }
                            }
                        }
                    }
                }
            }
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
            mysql:{$conf->mysqlVersion}");

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
        $envvars['AVC_SRC_DIR'] = $srcdir;
        $envvars['VIDEO_OUTPUT_DIR'] = "{$workingdir}/tests/_output/video";
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
        $envvars['PLUGINS'] = '"' . join(' ', $conf->downloadPlugins) . '"';
        if (!empty($conf->downloadThemes)) {
            $envvars['THEMES'] = '"' . join(' ', $conf->downloadThemes) . '"';
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
            -v /var/run/docker.sock:/var/run/docker.sock \
            {$volume} \
            {$wpimage}");

        if ($conf->useSelenoid) {
            $bjson = '';
            if (file_exists($bfile)) {
                $bjson = "-v {$workingdir}/tests/browsers.json:/etc/selenoid/browsers.json:ro";
            }
            passthru("docker run -d --name {$conf::$selenoidc} \
                --network {$conf::$network} \
                --expose {$conf::$selenoidPort} \
                -v /var/run/docker.sock:/var/run/docker.sock \
                -v {$workingdir}/tests/_output/video/:/opt/selenoid/video/ \
                -v {$workingdir}/tests/_output/logs/:/opt/selenoid/logs/ \
                {$bjson} \
                -e OVERRIDE_VIDEO_OUTPUT_DIR={$workingdir}/tests/_output/video/ \
                aerokube/selenoid:1.10.3 -container-network {$conf::$network} \
                -log-output-dir /opt/selenoid/logs \
                -video-recorder-image aivec/selenoid-video-recorder");
        }

        passthru("docker logs -f {$conf->container}");
    }
}
