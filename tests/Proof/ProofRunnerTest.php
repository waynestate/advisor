<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Proof;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Proof\ProofRunner;
use Waynestate\Advisor\Tests\Support\TempDir;

/**
 * The proof run against a tiny corpus on disk: a clean unit passes, a broken
 * advisor binary is a tool error, and a unit without PHP is not applicable.
 */
final class ProofRunnerTest extends TestCase
{
    use TempDir;

    private const ROOT = __DIR__ . '/../..';

    public function testACleanUnitPassesAndWritesOneLogLine(): void
    {
        $root = $this->tempDir();
        $corpus = $root . '/corpus';
        $unitId = 'demo-app';
        $this->fixtureUnit($corpus, $unitId);
        $units = $this->unitsFile($root, [[ $unitId, $unitId, 'app' ]]);
        $proctor = $this->fakeProctor($root, 'proctor.php');
        $booster = $this->fakeBooster($root, 'booster.php');
        $out = $root . '/out/proof.jsonl';
        mkdir($root . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($root, $units, $corpus, $proctor, $booster, [
            'date' => '2026-11-05',
            'timeout' => '15',
        ]))->run();

        $this->assertSame(0, $result['exit']);
        $this->assertCount(1, $result['lines']);
        $line = $result['lines'][0];
        $this->assertSame('advisor', $line['project']);
        $this->assertSame('0.1.5', $line['tool_version']);
        $this->assertNull($line['tool_commit']);
        $this->assertSame($unitId, $line['unit_id']);
        $this->assertSame('app', $line['unit_kind']);
        $this->assertNull($line['unit_commit'], 'The fixture has no git history.');
        $this->assertSame('advisor mcp; tools/call run_proctor_check, upgrade_path, policy_lookup', $line['command']);
        $this->assertStringStartsWith('php ', $line['image']);
        $this->assertSame(0, $line['exit']);
        $this->assertIsInt($line['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $line['duration_ms']);
        $this->assertSame('pass', $line['outcome']);
        $this->assertSame('2026-11-05', $line['date']);

        $detail = $line['detail'];
        $this->assertSame(['run_proctor_check', 'upgrade_path', 'policy_lookup'], array_keys($detail['calls']));
        foreach ($detail['calls'] as $name => $call) {
            $this->assertTrue($call['ok'], $name);
            $this->assertFalse($call['refused'], $name);
            $this->assertIsInt($call['ms'], $name);
        }
        $this->assertSame([], $detail['proctor_failed_checks']);
        $this->assertSame(6, $detail['proctor_findings']);
        $this->assertSame(1, $detail['hops']);
        $this->assertSame(0, $detail['warnings']);
        $this->assertSame('security', $detail['policy_state']);

        $this->assertSame(['pass' => 1, 'flagged' => 0, 'tool-error' => 0, 'not-applicable' => 0], $result['summary']['all']);
        $this->assertSame(['pass' => 1, 'flagged' => 0, 'tool-error' => 0, 'not-applicable' => 0], $result['summary']['apps']);

        $this->assertFileExists($out);
        $rawLines = file($out, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($rawLines);
        $this->assertCount(1, $rawLines);
        $decoded = json_decode((string) $rawLines[0], true);
        $this->assertSame($line, $decoded);
        $this->assertStringNotContainsString($root, (string) $rawLines[0], 'No scratch path may reach the log.');

        $this->assertDirectoryDoesNotExist($root . '/work/' . $unitId, 'The scratch copy must be removed.');
    }

    public function testABrokenAdvisorBinaryIsAToolError(): void
    {
        $root = $this->tempDir();
        $corpus = $root . '/corpus';
        $unitId = 'demo-app';
        $this->fixtureUnit($corpus, $unitId);
        $units = $this->unitsFile($root, [[ $unitId, $unitId, 'app' ]]);
        $proctor = $this->fakeProctor($root, 'proctor.php');
        $booster = $this->fakeBooster($root, 'booster.php');
        $broken = $root . '/broken-advisor.php';
        $this->put($broken, "<?php\nthis is not valid php at all;\n");
        $out = $root . '/out/proof.jsonl';
        mkdir($root . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($root, $units, $corpus, $proctor, $booster, [
            'advisor-bin' => $broken,
            'timeout' => '15',
        ]))->run();

        $this->assertSame(1, $result['exit'], 'A tool error makes the whole run exit 1.');
        $line = $result['lines'][0];
        $this->assertSame('tool-error', $line['outcome']);
        $this->assertNotNull($line['exit']);
        $this->assertNotEmpty($line['detail']['error']);
        $this->assertStringNotContainsString($root, (string) $line['detail']['error'], 'No scratch path may reach the error text.');
        $this->assertSame(1, $result['summary']['all']['tool-error']);
        $this->assertFileExists($out);
        $this->assertDirectoryDoesNotExist($root . '/work/' . $unitId, 'The scratch copy must be removed.');
    }

    public function testAUnitWithoutPhpIsNotApplicable(): void
    {
        $root = $this->tempDir();
        $corpus = $root . '/corpus';
        $unitId = 'docs-only';
        $this->put($corpus . '/' . $unitId . '/README.md', "# Not an app\n");
        $units = $this->unitsFile($root, [[ $unitId, $unitId, 'library' ]]);
        $proctor = $this->fakeProctor($root, 'proctor.php');
        $booster = $this->fakeBooster($root, 'booster.php');
        $out = $root . '/out/proof.jsonl';
        mkdir($root . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($root, $units, $corpus, $proctor, $booster, [
            'timeout' => '15',
        ]))->run();

        $this->assertSame(0, $result['exit']);
        $line = $result['lines'][0];
        $this->assertSame('not-applicable', $line['outcome']);
        $this->assertSame('advisor mcp', $line['command']);
        $this->assertNull($line['exit']);
        $this->assertSame([], $line['detail']);
        $this->assertSame(1, $result['summary']['all']['not-applicable']);
        $this->assertSame(0, array_sum($result['summary']['apps']), 'A library never counts toward the app totals.');
        $this->assertFileExists($out);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rows
     */
    private function unitsFile(string $root, array $rows): string
    {
        $path = $root . '/units.txt';
        $lines = [];
        foreach ($rows as [$id, $dir, $kind]) {
            $lines[] = "{$id} {$dir} {$kind}";
        }
        $this->put($path, implode("\n", $lines) . "\n");

        return $path;
    }

    private function fixtureUnit(string $corpus, string $id): void
    {
        $this->put($corpus . '/' . $id . '/composer.json', json_encode([
            'name' => 'example/demo',
            'require' => ['php' => '^8.3'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->put($corpus . '/' . $id . '/src/index.php', "<?php\n\n// A small app for the dress rehearsal.\necho 'hello';\n");
    }

    private function fakeProctor(string $root, string $name): string
    {
        $path = $root . '/' . $name;
        $findings = [];
        foreach (['php-floor', 'unbounded-constraints', 'lock-committed', 'portability', 'mysql56', 'overrides'] as $check) {
            $findings[] = ['check_id' => $check, 'status' => 'pass', 'message' => 'fine'];
        }
        $this->put($path, '<?php' . "\n"
            . 'echo json_encode(' . var_export(['version' => '0.1.0', 'findings' => $findings], true) . ");\n"
            . "exit(0);\n");

        return $path;
    }

    private function fakeBooster(string $root, string $name): string
    {
        $path = $root . '/' . $name;
        $plan = [
            'project' => ['name' => 'example/demo'],
            'today' => '2026-11-05',
            'target_php' => '8.4',
            'steps' => [['action' => 'bump php to 8.4']],
            'warnings' => [],
        ];
        $this->put($path, '<?php' . "\n"
            . 'echo json_encode(' . var_export($plan, true) . ");\n"
            . "exit(0);\n");

        return $path;
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function proofOptions(string $root, string $units, string $corpus, string $proctor, string $booster, array $extra = []): array
    {
        return $extra + [
            'units' => $units,
            'corpus' => $corpus,
            'work' => $root . '/work',
            'out' => $root . '/out/proof.jsonl',
            'proctor-bin' => $proctor,
            'booster-bin' => $booster,
            'advisor-bin' => self::ROOT . '/bin/advisor',
        ];
    }
}
