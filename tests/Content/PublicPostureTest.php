<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Advisor;

/**
 * ADVISOR is a public repository, so the bundled content must carry no
 * internal names: no test hosts, no personal accounts, no paths into the
 * coordination repo. The generator sanitizes what the sources still hold;
 * this test is the gate that fails, with file and line, on anything missed.
 */
final class PublicPostureTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN = [
        'GitLab',
        'gitlab.example',
        'graphite',
        'an internal host',
        'an internal host',
        'an internal host',
        'ck7939',
        'ck7939',
        'coordination repo',
        'design/ADR',
        'design/instance-runner',
    ];

    public function testTheBundledContentCarriesNoInternalNames(): void
    {
        $dir = Advisor::dataDir();
        $this->assertDirectoryExists($dir, 'data/ is missing. Run `composer content:generate`.');

        $hits = [];
        foreach ($this->files($dir) as $relative) {
            $lines = explode("\n", (string) file_get_contents($dir . '/' . $relative));
            foreach ($lines as $number => $line) {
                foreach (self::FORBIDDEN as $word) {
                    if (stripos($line, $word) !== false) {
                        $hits[] = sprintf('data/%s:%d: %s — %s', $relative, $number + 1, $word, trim($line));
                    }
                }
            }
        }

        $this->assertSame([], $hits, 'The bundled content carries internal names:' . ($hits === [] ? '' : "\n" . implode("\n", $hits)));
    }

    /**
     * @return list<string> every file under the folder, as a sorted relative path
     */
    private function files(string $dir): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            }
        }
        sort($out);

        return $out;
    }
}
