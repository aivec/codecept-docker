<?php

declare(strict_types=1);

use Aivec\WordPress\CodeceptDocker\ConfigValidator;
use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use PHPUnit\Framework\TestCase;

final class ValidateConfigTest extends TestCase
{
    public function invalidConfigs(): array {
        return [
            'namespace empty' => [
                [
                    'namespace' => '',
                    'projectType' => 'library',
                ],
                '/^namespace/',
            ],
            'namespace invalid' => [
                [
                    'namespace' => 'ネームスペース',
                    'projectType' => 'library',
                ],
                '/^namespace/',
            ],
            'projectType empty' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => '',
                ],
                '/^projectType/',
            ],
            'projectType invalid' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'corn',
                ],
                '/^projectType/',
            ],
            'wordpressVersion invalid' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'wordpressVersion' => 'バージョン',
                ],
                '/^wordpressVersion/',
            ],
            'useSelenoid invalid' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'useSelenoid' => 'huh?',
                ],
                '/^useSelenoid/',
            ],
            'language invalid' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'language' => '言語',
                ],
                '/^language/',
            ],
            'ssh not array' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ssh' => 'invalid',
                ],
                '/^ssh/',
            ],
            'ftp not array' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ftp' => 'invalid',
                ],
                '/^ftp/',
            ],
            'downloadPlugins not array' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'downloadPlugins' => 'invalid',
                ],
                '/^downloadPlugins/',
            ],
            'downloadPlugins contains duplicates' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'downloadPlugins' => [
                        'my-plugin-1',
                        'my-plugin-1',
                    ],
                ],
                '/^downloadPlugins/',
            ],
            'downloadThemes not array' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'downloadThemes' => 'invalid',
                ],
                '/^downloadThemes/',
            ],
            'downloadThemes contains duplicates' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'downloadThemes' => [
                        'my-theme-1',
                        'my-theme-1',
                    ],
                ],
                '/^downloadThemes/',
            ],
            'ssh missing requiredWith even if plugins/themes are empty' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ssh' => [
                        [
                            'plugins' => [],
                            'themes' => [],
                        ],
                    ],
                ],
                '/.*is required.*/',
            ],
            'ssh missing requiredWith' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ssh' => [
                        [
                            'plugins' => ['my-plugin'],
                            'themes' => [],
                        ],
                    ],
                ],
                '/.*is required.*/',
            ],
            'ssh plugins contains duplicates' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ssh' => [
                        [
                            'privateKeyPath' => __DIR__ . '/test_private_key.pem',
                            'host' => 'myserver.com',
                            'user' => 'admin',
                            'plugins' => ['my-plugin', 'my-plugin'],
                            'themes' => [],
                        ],
                    ],
                ],
                '/.*unique elements only*/',
            ],
            'ssh themes contains duplicates' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ssh' => [
                        [
                            'privateKeyPath' => __DIR__ . '/test_private_key.pem',
                            'host' => 'myserver.com',
                            'user' => 'admin',
                            'plugins' => [],
                            'themes' => ['my-theme', 'my-theme'],
                        ],
                    ],
                ],
                '/.*unique elements only*/',
            ],
            'ssh privateKeyPath file doesnt exist' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ssh' => [
                        [
                            'privateKeyPath' => __DIR__ . '/doesntexist.pem',
                            'host' => 'myserver.com',
                            'user' => 'admin',
                            'plugins' => [],
                            'themes' => [],
                        ],
                    ],
                ],
                '/.*does not exist*/',
            ],

            'ftp missing requiredWith' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ftp' => [
                        [
                            'plugins' => ['my-plugin'],
                            'themes' => [],
                        ],
                    ],
                ],
                '/.*is required.*/',
            ],
            'ftp plugins contains duplicates' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ftp' => [
                        [
                            'host' => 'myserver.com',
                            'user' => 'admin',
                            'plugins' => ['my-plugin', 'my-plugin'],
                            'themes' => [],
                        ],
                    ],
                ],
                '/.*unique elements only*/',
            ],
            'ftp themes contains duplicates' => [
                [
                    'namespace' => 'my-container',
                    'projectType' => 'plugin',
                    'ftp' => [
                        [
                            'host' => 'myserver.com',
                            'user' => 'admin',
                            'plugins' => [],
                            'themes' => ['my-theme', 'my-theme'],
                        ],
                    ],
                ],
                '/.*unique elements only*/',
            ],
        ];
    }

    /**
     * @dataProvider invalidConfigs
     */
    public function testConfigValidator($config, $pattern): void {
        if (!empty($pattern)) {
            $this->expectExceptionMessageMatches($pattern);
        }
        $this->expectException(InvalidConfigException::class);
        ConfigValidator::validateConfig($config);
    }
}
