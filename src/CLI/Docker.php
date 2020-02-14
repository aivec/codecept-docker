<?php
namespace Aivec\WordPress\CodeceptDocker\CLI;

use Aivec\WordPress\CodeceptDocker;

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
     * Initializes Docker containers and creates Codeception scaffolding
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        $pvolumes = [];
        // $initscript = CodeceptDocker::getAbsPath() . CodeceptDocker::VENDORDIR . '/initwp.sh';
        // $pvolumes = ['-v ' . $initscript . ':/docker-entrypoint-initwp.d/initwp.sh'];
        $composerCacheDir = CodeceptDocker\Utils::getCacheDir();
        if (!empty($composerCacheDir)) {
            $pvolumes[] = '-v ' . $composerCacheDir . ':/.composer/cache:rw';
        }
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
        $bridgeip = $res[0];
        
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
                --env DOCKER_BRIDGE_IP=' . $bridgeip . ' \
                --env FTP_CONFIGS=\'' . json_encode($this->config->ftp) . '\' \
                --env LANG=' . $this->config->lang . ' \
                ' . $pvolume . ' wpcodecept');
        }


        $this->dockerExec('composer require --dev lucatume/wp-browser');
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
        $env[] = 'TEST_SITE_WP_DOMAIN="http://' . $this->config->dockermeta['acceptance']['containers']['wordpress'] . '"';
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

        if (!file_exists(CodeceptDocker::getAbsPath() . '/codeception.dist.yml')) {
            $this->dockerExec('cp ' . $vendordir . '/conf/codeception.dist.yml codeception.dist.yml');
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
            $this->dockerExec('./vendor/bin/codecept g:helper Acceptance');
            $this->dockerExec('./vendor/bin/codecept g:helper Functional');
            $this->dockerExec('./vendor/bin/codecept g:helper Unit');
            $this->dockerExec('./vendor/bin/codecept g:helper Wpunit');
            $this->dockerExec('./vendor/bin/codecept build');
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
        }
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
        $dockerexec = 'docker exec -it --user 1000:1000 ' . $this->config->dockermeta['integration']['containers']['wordpress'] . ' /bin/bash -c \'cd ' . $path . '&& ';
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
}
