<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Content\Denylist;

/**
 * ADVISOR is a public repository, so the bundled content must carry no
 * internal names. The public patterns (generic shapes) are always checked;
 * the private strings (hostnames, people) are loaded from the file named by
 * the ADVISOR_PRIVATE_DENYLIST environment variable and checked only when
 * the file is present. The generator sanitizes what the sources still hold;
 * this test is the gate that fails, with file and line, on anything missed.
 */
final class PublicPostureTest extends TestCase
{
    /**
     * Public patterns: generic shapes that reveal nothing. These are always
     * checked, regardless of whether the private denylist is present.
     *
     * @var list<string> PCRE patterns
     */
    private const PUBLIC_PATTERNS = [
        '#\bADR-\d{4}\b#',
        '#\bD-\d{3}\b#',
        '#design/ADR#i',
        '#design/[a-z-]+\.md#i',
        '#coordination repo#i',
    ];

    public function testTheBundledContentCarriesNoPublicPatterns(): void
    {
        $dir = Advisor::dataDir();
        $this->assertDirectoryExists($dir, 'data/ is missing. Run `composer content:generate`.');

        $hits = [];
        foreach ($this->files($dir) as $relative) {
            $lines = explode("\n", (string) file_get_contents($dir . '/' . $relative));
            foreach ($lines as $number => $line) {
                foreach (self::PUBLIC_PATTERNS as $pattern) {
                    if (preg_match($pattern, $line) === 1) {
                        $hits[] = sprintf('data/%s:%d: %s: %s', $relative, $number + 1, $pattern, trim($line));
                    }
                }
            }
        }

        $this->assertSame([], $hits, 'The bundled content carries public patterns:' . ($hits === [] ? '' : "\n" . implode("\n", $hits)));
    }

    public function testTheBundledContentCarriesNoPrivateStrings(): void
    {
        $denylist = Denylist::fromEnv();
        if ($denylist->isEmpty()) {
            $this->markTestSkipped('ADVISOR_PRIVATE_DENYLIST is not set; the private part is skipped.');
        }

        $dir = Advisor::dataDir();
        $this->assertDirectoryExists($dir, 'data/ is missing. Run `composer content:generate`.');

        $hits = [];
        foreach ($this->files($dir) as $relative) {
            $lines = explode("\n", (string) file_get_contents($dir . '/' . $relative));
            foreach ($lines as $number => $line) {
                foreach ($denylist->find($line) as $match) {
                    $hits[] = sprintf('data/%s:%d: %s: %s', $relative, $number + 1, $match, trim($line));
                }
            }
        }

        $this->assertSame([], $hits, 'The bundled content carries private strings:' . ($hits === [] ? '' : "\n" . implode("\n", $hits)));
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
