<?php

namespace Aivec\WordPress\CodeceptDocker;

use Aivec\WordPress\CodeceptDocker\Errors\InvalidConfigException;
use Valitron\Validator;

/**
 * Validates config format/values
 */
class ConfigValidator
{
    /**
     * Validates all config values
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $conf
     * @return void
     * @throws InvalidConfigException Thrown if `$conf` is invalid.
     */
    public static function validateConfig(array $conf): void {
        $v = new Validator($conf);
        $v->rules([
            'required' => [
                ['namespace'],
                ['projectType'],
            ],
            'optional' => [
                ['wordpressVersion'],
                ['language'],
                ['useSelenoid'],
                ['ssh'],
                ['ftp'],
                ['downloadPlugins'],
                ['downloadThemes'],
            ],
            'ascii' => [
                ['namespace'],
                ['wordpressVersion'],
                ['language'],
            ],
            'boolean' => [
                ['useSelenoid'],
            ],
            'array' => [
                ['ssh'],
                ['ftp'],
                ['downloadPlugins'],
                ['downloadThemes'],
            ],
            'containsUnique' => [
                ['downloadPlugins'],
                ['downloadThemes'],
            ],
        ]);
        $v->rule(
            'in',
            'projectType',
            ['library', 'plugin', 'theme']
        )->message('{field} must be one of "library", "plugin", or "theme"');

        if (!$v->validate()) {
            $errors = is_array($v->errors()) ? $v->errors() : [];
            throw new InvalidConfigException($errors);
        }

        if (!empty($conf['ssh'])) {
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
            foreach ($conf['ssh'] as $ssh) {
                $v = new Validator($ssh);
                $v->rules([
                    'optional' => [
                        ['plugins'],
                        ['themes'],
                    ],
                    'array' => [
                        ['plugins'],
                        ['themes'],
                    ],
                    'containsUnique' => [
                        ['plugins'],
                        ['themes'],
                    ],
                    'fileNotFound' => 'privateKeyPath',
                ]);
                $v->rule('requiredWith', 'privateKeyPath', ['plugins', 'themes']);
                $v->rule('requiredWith', 'user', ['plugins', 'themes']);
                $v->rule('requiredWith', 'host', ['plugins', 'themes']);

                if (!$v->validate()) {
                    $errors = is_array($v->errors()) ? $v->errors() : [];
                    throw new InvalidConfigException($errors);
                }
            }
        }

        if (!empty($conf['ftp'])) {
            foreach ($conf['ftp'] as $ftp) {
                $v = new Validator($ftp);
                $v->rules([
                    'optional' => [
                        ['password'],
                        ['plugins'],
                        ['themes'],
                    ],
                    'array' => [
                        ['plugins'],
                        ['themes'],
                    ],
                    'containsUnique' => [
                        ['plugins'],
                        ['themes'],
                    ],
                ]);
                $v->rule('requiredWith', 'user', ['plugins', 'themes']);
                $v->rule('requiredWith', 'host', ['plugins', 'themes']);

                if (!$v->validate()) {
                    $errors = is_array($v->errors()) ? $v->errors() : [];
                    throw new InvalidConfigException($errors);
                }
            }
        }
    }
}
