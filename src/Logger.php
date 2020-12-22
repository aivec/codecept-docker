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
     * Prints config value error
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $key Config key
     * @param string $message
     * @return void
     */
    public function valueError(string $key, string $message): void {
        print '[key: ' . $this->yellow('"' . $key . '"') . ']: ' . $message . "\n";
    }

    /**
     * Prints red message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return string
     */
    public function red(string $message): string {
        return self::RED . $message . self::NC;
    }

    /**
     * Prints green message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return string
     */
    public function green(string $message): string {
        return self::GREEN . $message . self::NC;
    }

    /**
     * Prints cyan message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return string
     */
    public function cyan(string $message): string {
        return self::CYAN . $message . self::NC;
    }

    /**
     * Prints white message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return string
     */
    public function white(string $message): string {
        return self::WHITE . $message . self::NC;
    }

    /**
     * Prints yellow message
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @return string
     */
    public function yellow(string $message): string {
        return self::YELLOW . $message . self::NC;
    }
}
