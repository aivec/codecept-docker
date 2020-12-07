<?php

namespace Aivec\WordPress\CodeceptDocker;

/**
 * Console logger
 */
class Logger
{
    public const RED = "\x1b[31m";
    public const GREEN = "\x1b[32m";
    public const CYAN = "\x1b[36m";
    public const WHITE = "\x1b[37m";
    public const YELLOW = "\x1b[33m";
    public const NC = "\x1b[0m";

    public const LEVELS = ['info', 'warn', 'error'];

    /**
     * Returns log level header strings
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getHeaders(): array {
        return [
            'info' => self::CYAN . '[INFO]' . self::NC,
            'warn' => self::YELLOW . '[WARNING]' . self::NC,
            'error' => self::RED . '[FATAL]' . self::NC,
        ];
    }

    /**
     * Logs message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $level
     * @param mixed  $message
     * @return void
     */
    public function log(string $level, $message): void {
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        if (in_array($level, self::LEVELS, true)) {
            print "\n" . $this->getHeaders()[$level] . ' ' . $message . "\n";
        } else {
            print "\n" . '[' . $level . '] ' . $message . "\n";
        }
    }

    /**
     * Prints info level message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function info(string $message): void {
        $this->log('info', $message);
    }

    /**
     * Prints warn level message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function warn(string $message): void {
        $this->log('warn', $message);
    }

    /**
     * Prints error level message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function error(string $message): void {
        $this->log('error', $message);
    }

    /**
     * Prints context of syntax error
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param mixed $value
     * @return void
     */
    public function logContext($value): void {
        print "\nvalue: " . (string)$value;
    }

    /**
     * Prints syntax error details
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @param string $fileName
     * @return void
     */
    public function syntaxError(string $message, string $fileName): void {
        print "\n" . $this->getHeaders()['error'] . $fileName . ' SYNTAX ERROR' . "\n";
        print self::CYAN . 'details: ' . self::NC . $message;
    }

    /**
     * Prints red message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function red(string $message): void {
        print self::RED . $message . self::NC;
    }

    /**
     * Prints green message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function green(string $message): void {
        print self::GREEN . $message . self::NC;
    }

    /**
     * Prints cyan message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function cyan(string $message): void {
        print self::CYAN . $message . self::NC;
    }

    /**
     * Prints white message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function white(string $message): void {
        print self::WHITE . $message . self::NC;
    }

    /**
     * Prints yellow message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return void
     */
    public function yellow(string $message): void {
        print self::YELLOW . $message . self::NC;
    }
}
