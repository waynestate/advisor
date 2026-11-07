<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Proof;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Proof\ProofRunner;
use Waynestate\Advisor\Tests\Support\TempDir;

/**
 * The proof run against a tiny corpus on disk: a clean unit passes, a failed
 * PROCTOR check flags the unit, a broken advisor binary is a tool error, a
 * unit without PHP is not applicable, secrets stay out of the scratch copy,
 * the log holds no paths or unit directory names, and the exit code follows
 * the tool errors.
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
        $this->assertSame('0.1.6', $line['tool_version']);
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

    public function testAFailedProctorCheckFlagsTheUnit(): void
    {
        $root = $this->tempDir();
        $corpus = $root . '/corpus';
        $unitId = 'demo-app';
        $this->fixtureUnit($corpus, $unitId);
        $units = $this->unitsFile($root, [[ $unitId, $unitId, 'app' ]]);
        $proctor = $this->fakeProctor($root, 'proctor-fail.php', ['mysql56']);
        $booster = $this->fakeBooster($root, 'booster.php');
        $out = $root . '/out/proof.jsonl';
        mkdir($root . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($root, $units, $corpus, $proctor, $booster, [
            'timeout' => '15',
        ]))->run();

        $this->assertSame(0, $result['exit'], 'A flagged unit is news, not a tool error.');
        $line = $result['lines'][0];
        $this->assertSame('flagged', $line['outcome']);
        $detail = $line['detail'];
        $this->assertSame(['mysql56'], $detail['proctor_failed_checks']);
        $this->assertSame(6, $detail['proctor_findings']);
        $this->assertTrue($detail['calls']['run_proctor_check']['ok']);
        $this->assertFalse($detail['calls']['run_proctor_check']['refused']);
        $this->assertSame(1, $result['summary']['all']['flagged']);
        $this->assertFileExists($out);
    }

    public function testTheWorkCopyLeavesSecretsAndGitBehind(): void
    {
        $root = $this->tempDir();
        $corpus = $root . '/corpus';
        $unitId = 'demo-app';
        $this->fixtureUnit($corpus, $unitId);
        $this->put($corpus . '/' . $unitId . '/.env', "APP_KEY=topsecret\n");
        $this->put($corpus . '/' . $unitId . '/.env.example', "APP_KEY=\n");
        $this->put($corpus . '/' . $unitId . '/auth.json', "{}\n");
        $this->put($corpus . '/' . $unitId . '/.git/config', "[core]\n");
        $observed = $root . '/observed.txt';
        $units = $this->unitsFile($root, [[ $unitId, $unitId, 'app' ]]);
        $proctor = $this->observingProctor($root, 'proctor-observe.php', $observed);
        $booster = $this->fakeBooster($root, 'booster.php');
        $out = $root . '/out/proof.jsonl';
        mkdir($root . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($root, $units, $corpus, $proctor, $booster, [
            'timeout' => '15',
        ]))->run();

        $this->assertSame('pass', $result['lines'][0]['outcome'], 'The observing proctor still reports a clean pass.');
        $entries = file($observed, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($entries, 'The proctor ran inside the scratch copy and wrote its observation.');
        $this->assertContains('.env.example', $entries, '.env.example comes along with the copy.');
        $this->assertContains('composer.json', $entries);
        $this->assertNotContains('.env', $entries, '.env stays behind.');
        $this->assertNotContains('auth.json', $entries, 'auth.json stays behind.');
        $this->assertNotContains('.git', $entries, 'the git history stays behind.');
    }

    public function testTheLogHoldsNoPathsOrUnitNames(): void
    {
        $root = $this->tempDir();
        $corpus = $root . '/corpus';
        $unitId = 'first-unit';
        $dirName = 'zeta-demo';
        $this->fixtureUnit($corpus, $dirName);
        $units = $this->unitsFile($root, [[ $unitId, $dirName, 'app' ]]);
        $proctor = $this->fakeProctor($root, 'proctor.php');
        $booster = $this->fakeBooster($root, 'booster.php');
        $out = $root . '/out/proof.jsonl';
        $work = $root . '/work';
        mkdir($root . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($root, $units, $corpus, $proctor, $booster, [
            'timeout' => '15',
        ]))->run();

        $this->assertSame('pass', $result['lines'][0]['outcome']);
        $this->assertSame($unitId, $result['lines'][0]['unit_id']);
        $raw = (string) file_get_contents($out);
        $this->assertStringNotContainsString($work, $raw, 'No work directory path may reach the log.');
        $this->assertStringNotContainsString($corpus, $raw, 'No corpus path may reach the log.');
        $this->assertStringNotContainsString($dirName, $raw, 'No unit directory name may reach the log.');
    }

    public function testTheExitCodeFollowsToolErrors(): void
    {
        // No tool error: the run exits 0.
        $clean = $this->tempDir();
        $corpus = $clean . '/corpus';
        $unitId = 'demo-app';
        $this->fixtureUnit($corpus, $unitId);
        $units = $this->unitsFile($clean, [[ $unitId, $unitId, 'app' ]]);
        $proctor = $this->fakeProctor($clean, 'proctor.php');
        $booster = $this->fakeBooster($clean, 'booster.php');
        mkdir($clean . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($clean, $units, $corpus, $proctor, $booster, [
            'timeout' => '15',
        ]))->run();

        $this->assertSame(0, $result['exit'], 'Without a tool error the run exits 0.');
        $this->assertSame(0, $result['summary']['all']['tool-error']);

        // A tool error: the run exits 1.
        $broken = $this->tempDir();
        $corpus = $broken . '/corpus';
        $this->fixtureUnit($corpus, $unitId);
        $units = $this->unitsFile($broken, [[ $unitId, $unitId, 'app' ]]);
        $proctor = $this->fakeProctor($broken, 'proctor.php');
        $booster = $this->fakeBooster($broken, 'booster.php');
        $badAdvisor = $broken . '/broken-advisor.php';
        $this->put($badAdvisor, "<?php\nthis is not valid php at all;\n");
        mkdir($broken . '/out', 0775, true);

        $result = ProofRunner::fromOptions($this->proofOptions($broken, $units, $corpus, $proctor, $booster, [
            'advisor-bin' => $badAdvisor,
            'timeout' => '15',
        ]))->run();

        $this->assertSame(1, $result['exit'], 'A tool error makes the whole run exit 1.');
        $this->assertSame(1, $result['summary']['all']['tool-error']);
        $this->assertSame('tool-error', $result['lines'][0]['outcome']);
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

    /**
     * @param list<string> $failed
     * @return list<array{check_id: string, status: string, message: string}>
     */
    private function proctorFindings(array $failed = []): array
    {
        $findings = [];
        foreach (['php-floor', 'unbounded-constraints', 'lock-committed', 'portability', 'mysql56', 'overrides'] as $check) {
            $status = in_array($check, $failed, true) ? 'fail' : 'pass';
            $findings[] = ['check_id' => $check, 'status' => $status, 'message' => $status === 'pass' ? 'fine' : 'needs attention'];
        }

        return $findings;
    }

    /**
     * A PROCTOR that reports the given checks as failed and exits 1, like the real one does.
     *
     * @param list<string> $failed
     */
    private function fakeProctor(string $root, string $name, array $failed = []): string
    {
        $path = $root . '/' . $name;
        $this->put($path, '<?php' . "\n"
            . 'echo json_encode(' . var_export(['version' => '0.1.0', 'findings' => $this->proctorFindings($failed)], true) . ");\n"
            . 'exit(' . ($failed === [] ? 0 : 1) . ");\n");

        return $path;
    }

    /**
     * A passing PROCTOR that first records the names it sees at the top of the
     * app folder it was pointed at, so a test can inspect the scratch copy from inside.
     */
    private function observingProctor(string $root, string $name, string $observed): string
    {
        $path = $root . '/' . $name;
        $this->put($path, '<?php' . "\n"
            . '$appPath = "";' . "\n"
            . 'foreach ($argv ?? [] as $arg) {' . "\n"
            . '    if (str_starts_with($arg, "--path=")) {' . "\n"
            . '        $appPath = substr($arg, 7);' . "\n"
            . '    }' . "\n"
            . '}' . "\n"
            . '$entries = [];' . "\n"
            . 'if ($appPath !== "" && is_dir($appPath)) {' . "\n"
            . '    $entries = array_values(array_diff(scandir($appPath) ?: [], [".", ".."]));' . "\n"
            . '    sort($entries);' . "\n"
            . '}' . "\n"
            . 'file_put_contents(' . var_export($observed, true) . ', implode("\n", $entries) . "\n");' . "\n"
            . 'echo json_encode(' . var_export(['version' => '0.1.0', 'findings' => $this->proctorFindings()], true) . ");\n"
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
