<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waynestate\Advisor\Content\PartsTable;
use Waynestate\Advisor\Support\Json;
use Waynestate\Advisor\Tests\Support\TempDir;

/** The parts table: one render from the seed, written and checked between markers. */
final class PartsTableTest extends TestCase
{
    use TempDir;

    /** @return list<array{name: string, tagline: string}> */
    private function projects(): array
    {
        return [
            ['name' => 'WayneUI', 'tagline' => 'interface (project 0)'],
            ['name' => 'CREED', 'tagline' => 'version policy and written standards'],
            ['name' => 'PROCTOR', 'tagline' => 'checks an app'],
        ];
    }

    public function testRendersOneRowPerProjectInSeedOrder(): void
    {
        $this->assertSame(
            "| Part | What it is |\n|---|---|\n| **WayneUI** | interface (project 0) |\n| **CREED** | version policy and written standards |\n| **PROCTOR** | checks an app |\n",
            PartsTable::render($this->projects()),
        );
    }

    public function testWriteReplacesOnlyTheBlockBetweenTheMarkers(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "# Title\n\nBefore.\n\n<!-- stack-parts:start -->\n\nOld table.\n\n<!-- stack-parts:end -->\n\nAfter.\n");

        PartsTable::write($path, $this->projects());

        $this->assertSame(
            "# Title\n\nBefore.\n\n<!-- stack-parts:start -->\n\n" . PartsTable::GENERATED_NOTE . "\n\n| Part | What it is |\n|---|---|\n| **WayneUI** | interface (project 0) |\n| **CREED** | version policy and written standards |\n| **PROCTOR** | checks an app |\n\n<!-- stack-parts:end -->\n\nAfter.\n",
            (string) file_get_contents($path),
        );
    }

    public function testWriteIsIdempotent(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "<!-- stack-parts:start -->\n\n<!-- stack-parts:end -->\n");

        PartsTable::write($path, $this->projects());
        $first = (string) file_get_contents($path);
        PartsTable::write($path, $this->projects());

        $this->assertSame($first, (string) file_get_contents($path));
    }

    public function testWriteRefusesAFileWithoutBothMarkersAndLeavesItUntouched(): void
    {
        $path = $this->tempDir() . '/README.md';
        $before = "# Title\n\nNo markers here.\n";
        $this->put($path, $before);

        try {
            PartsTable::write($path, $this->projects());
            $this->fail('Writing a file without both markers should throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('missing its start marker', $e->getMessage());
        }

        $this->assertSame($before, (string) file_get_contents($path));
    }

    public function testWriteRefusesAFileWithOnlyTheEndMarker(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "<!-- stack-parts:end -->\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing its start marker');
        PartsTable::write($path, $this->projects());
    }

    public function testWriteRefusesMarkersInTheWrongOrder(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "<!-- stack-parts:end -->\n\n<!-- stack-parts:start -->\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('end marker before its start marker');
        PartsTable::write($path, $this->projects());
    }

    public function testCheckPassesWhenTheBlockMatches(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "<!-- stack-parts:start -->\n\n<!-- stack-parts:end -->\n");
        PartsTable::write($path, $this->projects());

        $this->assertSame([], PartsTable::check([$path], $this->projects()));
    }

    public function testCheckNamesEveryStaleFile(): void
    {
        $fresh = $this->tempDir() . '/fresh.md';
        $stale = $this->tempDir() . '/stale.md';
        $unmarked = $this->tempDir() . '/unmarked.md';
        $this->put($fresh, "<!-- stack-parts:start -->\n\n<!-- stack-parts:end -->\n");
        PartsTable::write($fresh, $this->projects());
        $this->put($stale, "<!-- stack-parts:start -->\n\n| Part | What it is |\n|---|---|\n| **WayneUI** | something else |\n\n<!-- stack-parts:end -->\n");
        $this->put($unmarked, "# No markers\n");

        $this->assertSame(
            [$stale, "stack-parts: {$unmarked} is missing its start marker (<!-- stack-parts:start -->)."],
            PartsTable::check([$fresh, $stale, $unmarked], $this->projects()),
        );
    }

    public function testTheSeedCarriesATaglineForEveryProject(): void
    {
        $seed = Json::decodeFile(dirname(__DIR__, 2) . '/generator/projects.seed.json');
        $projects = $seed['projects'] ?? [];

        $this->assertCount(14, $projects, 'The seed should list the fourteen stack parts.');
        $this->assertSame(
            ['wayne-ui', 'creed', 'proctor', 'sesame', 'helmet', 'sourdough', 'preflight', 'pinstripe', 'booster', 'rollcall', 'drawbridge', 'squire', 'advisor', 'weld'],
            array_column($projects, 'id'),
        );
        foreach ($projects as $project) {
            $this->assertIsString($project['tagline'] ?? null, ($project['id'] ?? '?') . ' has no tagline.');
            $this->assertNotSame('', $project['tagline'], ($project['id'] ?? '?') . ' has an empty tagline.');
        }
    }
}
