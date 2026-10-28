<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

/**
 * Keeps stray output off the protocol stream.
 *
 * On stdio, stdout carries only MCP messages. The SDK writes them straight to
 * the STDOUT stream, so anything that goes through PHP's output layer (an
 * echo, a notice or deprecation printed by display_errors) is not a message.
 * This turns that layer into a pipe to stderr, and turns PHP's own errors
 * into stderr lines as well.
 */
final class StdoutGuard
{
    private static bool $installed = false;

    /**
     * @param resource|null $stderr defaults to STDERR
     */
    public static function install($stderr = null): void
    {
        if (self::$installed) {
            return;
        }
        self::$installed = true;
        $stderr ??= STDERR;

        ini_set('display_errors', 'stderr');
        ini_set('html_errors', '0');
        ini_set('log_errors', '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($stderr): bool {
            if ((error_reporting() & $severity) !== 0) {
                fwrite($stderr, sprintf("[advisor php] %s in %s:%d\n", $message, basename($file), $line));
            }

            return true;
        });

        // Chunk size 1 flushes on every write, so nothing waits in a buffer.
        ob_start(static function (string $buffer) use ($stderr): string {
            if ($buffer !== '') {
                fwrite($stderr, "[advisor stray-output] " . $buffer);
            }

            return '';
        }, 1);
    }

    /** For tests: undo install() so PHPUnit gets its own handlers back. */
    public static function uninstall(): void
    {
        if (!self::$installed) {
            return;
        }
        self::$installed = false;
        restore_error_handler();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
