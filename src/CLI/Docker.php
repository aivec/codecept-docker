<?php
namespace Aivec\WordPress\Codeception\CLI;

use Aivec\WordPress\Codeception\CodeceptDocker;

/**
 * CLI methods related to Docker commands
 */
class Docker {
    
    /**
     * Dependency injected config model
     *
     * @var CodeceptDocker
     */
    private $config;

    /**
     * Initializes config member var
     *
     * @param CodeceptDocker $config
     */
    public function __construct(CodeceptDocker $config) {
        $this->config = $config;
    }

    /**
     * Spins up docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function createEnvironments() {
        $pvolumes = [];
        $pvolume = '';
        @mkdir(CodeceptDocker::getAbsPath() . '/tests', 0755);
        switch ($this->config->projectType) {
            case 'library':
                $pluginfile = CodeceptDocker::getAbsPath() . '/tests/implementation-plugin/implementation-plugin.php';
                if (!file_exists($pluginfile)) {
                    @mkdir(CodeceptDocker::getAbsPath() . '/tests/implementation-plugin', 0755);
                    @copy(
                        CodeceptDocker::getAbsPath() . CodeceptDocker::VENDORDIR . '/implementation-plugin.php',
                        $pluginfile
                    );
                }
                $pvolumes[] = '-v ' . CodeceptDocker::getAbsPath() . '/tests/implementation-plugin' . ':' . CodeceptDocker::WPROOT . '/wp-content/plugins/implementation-plugin';
                $pvolumes[] = '-v ' . CodeceptDocker::getAbsPath() . ':' . CodeceptDocker::WPROOT. '/wp-content/plugins/' . CodeceptDocker::getWorkingDirname();
                break;
            case 'plugin':
                $pvolumes[] = '-v ' . CodeceptDocker::getAbsPath() . ':' . CodeceptDocker::WPROOT. '/wp-content/plugins/' . CodeceptDocker::getWorkingDirname();
                break;
            case 'theme':
                $pvolumes[] = '-v ' . CodeceptDocker::getAbsPath() . ':' . CodeceptDocker::WPROOT. '/wp-content/themes/' . CodeceptDocker::getWorkingDirname();
                break;
        }

        $pvolume = join(' ', $pvolumes);
        
        // build docker image
        passthru('cd ' . CodeceptDocker::getAbsPath() . CodeceptDocker::VENDORDIR . ' && docker build . -t wpcodecept');
        
        // create CodeceptDocker network for wordpress-apache container and mysql container
        passthru('docker network create --attachable ' . $this->config->network);
        
        $res = [];
        exec("docker network inspect bridge -f '{{ (index .IPAM.Config 0).Gateway }}'", $res);
        $bridgeip = !empty($res[0]) ? '--env DOCKER_BRIDGE_IP=' . $res[0] : '';
        
        foreach ($this->config->dockermeta as $type => $info) {
            // create and run mysql container
            passthru('docker run -d --name ' . $info['containers']['db'] . ' \
                --network ' . $this->config->network . ' \
                --env MYSQL_DATABASE=' . $info['dbname'] . ' \
                --env MYSQL_USER=admin \
                --env MYSQL_PASSWORD=admin \
                --env MYSQL_ROOT_PASSWORD=root \
                -v ' . $info['volumes']['db'] . ':/var/lib/mysql \
                mysql:5.7');
            
            // create and run WordPress containers
            passthru('docker run -d --name ' . $info['containers']['wordpress'] . ' \
                --network ' . $this->config->network . ' \
                --env WORDPRESS_DB_HOST=' . $info['containers']['db'] . ' \
                --env WORDPRESS_DB_USER=root \
                --env WORDPRESS_DB_PASSWORD=root \
                --env WORDPRESS_DB_NAME=' . $info['dbname'] . ' \
                --env XDEBUG_PORT=' . $info['xdebugport'] . ' \
                ' . $bridgeip . ' \
                --env FTP_CONFIGS=\'' . json_encode($this->config->ftp) . '\' \
                --env LANG=' . $this->config->lang . ' \
                ' . $pvolume . ' wpcodecept');
            
            // change ownership of wp-content and plugins/themes directories to www-data:www-data so
            // WP-CLI doesn't fail
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content');
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content/plugins');
            passthru('docker exec -i ' . $info['containers']['wordpress'] . ' chown www-data:www-data wp-content/themes');
        }

        print("Waiting for MySQL containers to be ready...\n");
        foreach ($this->config->dockermeta as $type => $info) {
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
        $this->wpAcceptanceCLI('core install \
            --url=' . $this->config->dockermeta['acceptance']['containers']['wordpress'] . ' \
            --title=Tests \
            --admin_user=root --admin_password=root \
            --admin_email=admin@example.com');
        $this->wpIntegrationCLI('core install \
            --url=' . $this->config->dockermeta['integration']['containers']['wordpress'] . ' \
            --title=Tests \
            --admin_user=root --admin_password=root \
            --admin_email=admin@example.com');

        $this->installAndActivateLanguage();
        $this->installAndActivatePlugins();
    }

    /**
     * Initializes Docker containers and creates Codeception scaffolding
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        $this->createEnvironments();
        $this->generateScaffolding();
        passthru('composer dump-autoload --optimize');
    }

    /**
     * Installs and activates language defined in `codecept-docker.json` config file
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installAndActivateLanguage() {
        $this->wpAcceptanceCLI('language core install ' . $this->config->lang);
        $this->wpAcceptanceCLI('site switch-language ' . $this->config->lang);
        $this->wpIntegrationCLI('language core install ' . $this->config->lang);
        $this->wpIntegrationCLI('site switch-language ' . $this->config->lang);
    }

    /**
     * Installs and activates plugins defined in `codecept-docker.json` config file
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function installAndActivatePlugins() {
        foreach ($this->config->downloadPlugins as $plugin) {
            $this->wpAcceptanceCLI('plugin install ' . $plugin);
            $this->wpAcceptanceCLI('plugin activate ' . $plugin);
            $this->wpIntegrationCLI('plugin install ' . $plugin);
            $this->wpIntegrationCLI('plugin activate ' . $plugin);
        }

        
    }
    
    /**
     * Copies test scaffolding folders and files to project dir if they don't exist already
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function generateScaffolding() {
        $env = [];
        $env[] = 'WP_ROOT_FOLDER="' . CodeceptDocker::WPROOT . '"';
        $env[] = 'TEST_SITE_WP_ADMIN_PATH="/wp-admin"';
        $env[] = 'TEST_SITE_DB_NAME="' . $this->config->dockermeta['acceptance']['dbname'] . '"';
        $env[] = 'TEST_SITE_DB_HOST="' . $this->config->dockermeta['acceptance']['containers']['db'] . '"';
        $env[] = 'TEST_SITE_DB_USER="root"';
        $env[] = 'TEST_SITE_DB_PASSWORD="root"';
        $env[] = 'TEST_SITE_TABLE_PREFIX="wp_"';
        $env[] = 'TEST_DB_NAME="' . $this->config->dockermeta['integration']['dbname'] . '"';
        $env[] = 'TEST_DB_HOST="' . $this->config->dockermeta['integration']['containers']['db'] . '"';
        $env[] = 'TEST_DB_USER="root"';
        $env[] = 'TEST_DB_PASSWORD="root"';
        $env[] = 'TEST_TABLE_PREFIX="wp_"';
        $env[] = 'TEST_SITE_WP_URL="http://' . $this->config->dockermeta['acceptance']['containers']['wordpress'] . '"';
        $env[] = 'TEST_SITE_WP_DOMAIN="http://' . $this->config->dockermeta['integration']['containers']['wordpress'] . '"';
        $env[] = 'TEST_SITE_ADMIN_EMAIL="admin@example.com"';
        $env[] = 'TEST_SITE_ADMIN_USERNAME="root"';
        $env[] = 'TEST_SITE_ADMIN_PASSWORD="root"';
        @file_put_contents(
            CodeceptDocker::getAbsPath() . '/.env.testing',
            join("\n", $env)
        );

        $vendordir = './vendor/aivec/codecept-docker';
        $gitignore = "*\n";
        $gitignore .= '!.gitignore';

        if (!file_exists(CodeceptDocker::getAbsPath() . '/codeception.yml')) {
            $this->dockerExec('cp ' . $vendordir . '/conf/codeception.yml codeception.yml');
        }
        if (!file_exists(CodeceptDocker::getAbsPath() . '/tests/acceptance.suite.yml')) {
            $this->dockerExec('cp ' . $vendordir . '/conf/acceptance.suite.yml tests/acceptance.suite.yml');
        }
        if (!file_exists(CodeceptDocker::getAbsPath() . '/tests/functional.suite.yml')) {
            $this->dockerExec('cp ' . $vendordir . '/conf/functional.suite.yml tests/functional.suite.yml');
        }
        if (!file_exists(CodeceptDocker::getAbsPath() . '/tests/unit.suite.yml')) {
            $this->dockerExec('cp ' . $vendordir . '/conf/unit.suite.yml tests/unit.suite.yml');
        }
        if (!file_exists(CodeceptDocker::getAbsPath() . '/tests/wpunit.suite.yml')) {
            $this->dockerExec('cp ' . $vendordir . '/conf/wpunit.suite.yml tests/wpunit.suite.yml');
        }

        if (!is_dir(CodeceptDocker::getAbsPath() . '/tests/_support')) {
            $this->codecept('g:helper Unit');
            $this->codecept('g:helper Wpunit');
            $this->codecept('g:helper Acceptance');
            $this->codecept('g:helper Functional');
            $this->codecept('build');
            @file_put_contents(
                CodeceptDocker::getAbsPath() . '/tests/_support/_generated/.gitignore',
                $gitignore
            );
        }

        if (!is_dir(CodeceptDocker::getAbsPath() . '/tests/_data')) {
            $this->dockerExec('mkdir -p tests/_data');
            $this->dockerExec('touch tests/_data/.gitkeep');
        }
        if (!is_dir(CodeceptDocker::getAbsPath() . '/tests/_output')) {
            $this->dockerExec('mkdir -p tests/_output');
            $this->dockerExec('touch tests/_output/.gitkeep');
            @file_put_contents(
                CodeceptDocker::getAbsPath() . '/tests/_output/.gitignore',
                $gitignore
            );
        }

        // generate sample tests
        $this->codecept('g:wpunit wpunit Sample');
        $this->codecept('g:test unit Sample');
    }

    /**
     * Passes command/args to wp-cli container that operates on both acceptance and
     * integration WordPress installs
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function wpcli($command) {
        $this->wpAcceptanceCLI($command);
        $this->wpIntegrationCLI($command);
    }

    /**
     * Spins-up wp-cli container and executes command for acceptance install
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function wpAcceptanceCLI($command) {
        passthru('docker run -i --rm \
            --volumes-from ' . $this->config->dockermeta['acceptance']['containers']['wordpress'] . ' \
            --network ' . $this->config->network . ' \
            --user 33:33 -e HOME=/tmp \
            wordpress:cli ' . $command);
    }

    /**
     * Spins-up wp-cli container and executes command for integration install
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function wpIntegrationCLI($command) {
        passthru('docker run -i --rm \
            --volumes-from ' . $this->config->dockermeta['integration']['containers']['wordpress'] . ' \
            --network ' . $this->config->network . ' \
            --user 33:33 -e HOME=/tmp \
            wordpress:cli ' . $command);
    }

    /**
     * Wrapper for docker exec
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command
     * @return void
     */
    public function dockerExec($command) {
        $path = $this->config->projectType === 'theme' ? CodeceptDocker::WPROOT . '/wp-content/themes/' : CodeceptDocker::WPROOT . '/wp-content/plugins/';
        $path .= CodeceptDocker::getWorkingDirname();
        $dockerexec = 'docker exec -i --user 1000:1000 ' . $this->config->dockermeta['integration']['containers']['wordpress'] . ' /bin/bash -c \'cd ' . $path . '&& ';
        passthru($dockerexec . $command . '\'');
    }

    /**
     * Starts stopped Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function start() {
        foreach ($this->config->dockermeta as $type => $info) {
            passthru('docker start ' . $info['containers']['db']);
            passthru('docker start ' . $info['containers']['wordpress']);
        }
    }

    /**
     * Stops Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function stop() {
        foreach ($this->config->dockermeta as $type => $info) {
            passthru('docker stop ' . $info['containers']['wordpress']);
            passthru('docker stop ' . $info['containers']['db']);
        }
    }

    /**
     * Stops and removes Codeception Docker containers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function down() {
        foreach ($this->config->dockermeta as $type => $info) {
            passthru('docker stop ' . $info['containers']['wordpress']);
            passthru('docker stop ' . $info['containers']['db']);
            passthru('docker rm ' . $info['containers']['wordpress']);
            passthru('docker rm ' . $info['containers']['db']);
        }
        passthru('docker network rm ' . $this->config->network);
    }

    /**
     * Updates wordpress:latest and wordpress:cli images
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function updateImages() {
        passthru('docker image pull wordpress');
        passthru('docker image pull wordpress:cli');
    }

    /**
     * Passes command as is to codecept script in Docker container
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $command raw codecept command
     * @return void
     */
    public function codecept($command) {
        $this->dockerExec('./vendor/bin/codecept ' . $command);
    }
}
