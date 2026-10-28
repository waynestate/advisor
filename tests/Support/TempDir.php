<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use PHPUnit\Framework\Attributes\After;

/** Makes throw-away folders for a test and removes them afterwards. */
trait TempDir
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/advisor-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);
        $this->tempDirs[] = realpath($dir) ?: $dir;

        return realpath($dir) ?: $dir;
    }

    protected function put(string $path, string $content): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
    }

    #[After]
    protected function removeTempDirs(): void
    {
        foreach ($this->tempDirs as $dir) {
            self::remove($dir);
        }
        $this->tempDirs = [];
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }
}
