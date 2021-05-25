<?php

namespace Aivec\WordPress\CodeceptDocker\Errors;

use Exception;

/**
 * Exception for an invalid `codecept-docker.json` config
 */
class InvalidConfigException extends Exception
{
    /**
     * Array of key value error messages
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Constructs an `InvalidConfigException`
     *
     * Even if only one validation error was found it must be passed as an array
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $errors
     * @return void
     */
    public function __construct(array $errors) {
        $this->errors = $errors;
        $this->message = '';
        $index = 0;
        foreach ($errors as $key => $errorsmessages) {
            $this->message .= $index > 0 ? ' ' : '';
            $this->message .= $key . ': ' . join(':', $errorsmessages) . ';';
            $index++;
        }
    }

    /**
     * Getter for `errors`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }
}
