<?php

namespace Aivec\WordPress\CodeceptDocker;

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
        $this->configValidateProjectType();
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
     * @return void
     */
    public function configValidateProjectType(): void {
        self::validateProjectType(isset($this->config->conf['projectType']) ? $this->config->conf['projectType'] : '');
    }

    /**
     * Validates `projectType` field
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $project_type
     * @return void
     */
    public static function validateProjectType(string $project_type = ''): void {
        if (empty($project_type)) {
            (new Logger())->error('"projectType" is not defined. "projectType" must be one of "library", "plugin", or "theme"');
            echo "\r\n";
            exit(1);
        }

        if ($project_type !== 'library' && $project_type !== 'plugin' && $project_type !== 'theme') {
            (new Logger())->error('"projectType" must be one of "library", "plugin", or "theme"');
            echo "\r\n";
            exit(1);
        }
    }

    /**
     * Validates `ssh` field
     * 
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void 
     */
    public function configValidateSshRawConfig(): void {
        if (!isset($this->config->conf['ssh'])) {
            return;
        }

        $ssh = $this->config->conf['ssh'];
        if (!is_array($ssh)) {
            $this->logger->error('"ssh" must be an array');
        }

        self::validateSshRawConfig($ssh);
    }

    public static function validateSshRawConfig(array $ssh): void {
        
    }
}
