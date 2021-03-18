<?php

namespace Aivec\WordPress\CodeceptDocker;

use Valitron\Validator;

/**
 * Validates config format/values
 */
class ConfigValidator
{
    /**
     * Config object
     *
     * @var Config
     */
    private $config;

    /**
     * Logger object
     *
     * @var Logger
     */
    public $logger;

    /**
     * Initializes validator
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param Config $config
     * @return void
     */
    public function __construct(Config $config) {
        $this->config = $config;
        $this->logger = new Logger();
    }

    /**
     * Validates all config values
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function validateConfig(): void {
        $conf = $this->config->conf;
        $v = new Validator($conf);
        Validator::addRule(
            'fileNotFound',
            function ($field, $value, array $params, array $fields) {
                if (empty($value)) {
                    return false;
                }
                if (file_exists($value) === false) {
                    return false;
                }
                return file_get_contents($value) !== false;
            },
            'refers to a file that either does not exist or cannot be read.'
        );
        $v->rules([
            'required' => [
                ['projectType'],
                /* ['ssh.*.privateKeyPath'],
                ['ssh.*.user'],
                ['ssh.*.host'], */
            ],
            'optional' => [
                ['wordpressVersion'],
                ['ssh'],
                ['ftp'],
                ['downloadPlugins'],
                ['downloadThemes'],
            ],
            'array' => [
                ['ssh'],
                ['ftp'],
                ['downloadPlugins'],
                ['downloadThemes'],
                ['ssh.*.themes'],
                ['ssh.*.plugins'],
            ],
            'containsUnique' => [
                ['downloadPlugins'],
                ['downloadThemes'],
                ['ssh.*.themes'],
                ['ssh.*.plugins'],
            ],
            'fileNotFound' => 'ssh.*.privateKeyPath',
        ]);
        $v->rule(
            'in',
            'projectType',
            ['library', 'plugin', 'theme']
        )->message('{field} must be one of "library", "plugin", or "theme"');

        if (!$v->validate()) {
            $this->logger->error($this->logger->white('Error in ') . $this->logger->yellow('codecept-docker.json'));
            print "\n";
            foreach ($v->errors() as $key => $errors) {
                foreach ($errors as $emessage) {
                    $this->logger->valueError($key, $emessage);
                }
            }
            exit(1);
        }

        if (!empty($conf['ssh'])) {
            $index = 0;
            foreach ($conf['ssh'] as $ssh) {
                $this->config->conf['ssh'][$index]['privateKeyFilename'] = basename($ssh['privateKeyPath']);
                $index++;
            }
        }

        $this->configValidateNamespace();
    }

    /**
     * Validates `namespace` field
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function configValidateNamespace(): void {
        if (empty($this->config->conf['namespace'])) {
            $this->logger->warn('codecept-docker.json does not contain a "namespace" field, defaulting to project directory name as a prefix for containers');
        }
    }

    /**
     * Validates value of `projectType` key in JSON config
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $project_type
     * @return void
     */
    public static function validateProjectType($project_type) {
        if (empty($project_type)) {
            echo "\r\n";
            echo 'FATAL: "projectType" is not defined. "projectType" must be one of "library", "plugin", or "theme"';
            echo "\r\n";
            exit(1);
        }

        if ($project_type !== 'library' && $project_type !== 'plugin' && $project_type !== 'theme') {
            echo "\r\n";
            echo 'FATAL: "projectType" must be one of "library", "plugin", or "theme"';
            echo "\r\n";
            exit(1);
        }
    }
}
