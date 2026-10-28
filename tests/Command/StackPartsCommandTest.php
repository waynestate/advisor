<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Waynestate\Advisor\Command\StackPartsCommand;
use Waynestate\Advisor\Tests\Support\TempDir;

/** The stack-parts command: prints the table, writes and checks README blocks. */
final class StackPartsCommandTest extends TestCase
{
    use TempDir;

    private function tester(): CommandTester
    {
        return new CommandTester(new StackPartsCommand());
    }

    public function testWithNoOptionsItPrintsTheTable(): void
    {
        $tester = $this->tester();

        $this->assertSame(0, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringStartsWith("| Part | What it is |\n|---|---|\n", $display);
        $this->assertStringContainsString("| **WayneUI** | interface (project 0) |", $display);
        $this->assertStringContainsString("| **WELD** | the stack's name and its reference app |", $display);
        $this->assertSame(16, substr_count($display, "\n"), 'The table is a header, a separator and fourteen rows.');
    }

    public function testWriteUpdatesTheBlockAndCheckPassesAfter(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "<!-- stack-parts:start -->\n\n<!-- stack-parts:end -->\n");

        $this->assertSame(0, $this->tester()->execute(['--write' => $path]));
        $this->assertSame(0, $this->tester()->execute(['--check' => $path]));
    }

    public function testCheckFailsAndNamesAStaleFile(): void
    {
        $path = $this->tempDir() . '/README.md';
        $this->put($path, "<!-- stack-parts:start -->\n\n| Part | What it is |\n|---|---|\n| **WayneUI** | stale |\n\n<!-- stack-parts:end -->\n");

        $tester = $this->tester();

        $this->assertSame(1, $tester->execute(['--check' => $path]));
        $this->assertStringContainsString($path, $tester->getDisplay());
    }

    public function testWriteFailsWithExitTwoWhenTheMarkersAreMissing(): void
    {
        $path = $this->tempDir() . '/README.md';
        $before = "# No markers\n";
        $this->put($path, $before);

        $tester = $this->tester();

        $this->assertSame(2, $tester->execute(['--write' => $path]));
        $this->assertSame($before, (string) file_get_contents($path));
    }
}
