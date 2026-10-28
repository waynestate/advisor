<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Security\ProcessOutput;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Server\ToolOutcome;
use Waynestate\Advisor\Tests\Support\Boot;
use Waynestate\Advisor\Tests\Support\FakeProcessRunner;
use Waynestate\Advisor\Tests\Support\TempDir;

/** Each tool through the same ToolInvoker the adapters use, on the real bundled data. */
final class ToolsTest extends TestCase
{
    use TempDir;

    /**
     * @param array<string, mixed> $args
     */
    private function call(Booted $booted, string $tool, array $args = []): ToolOutcome
    {
        return $booted->invoker->invoke($booted->catalog->tools()[$tool], $args);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(ToolOutcome $outcome): array
    {
        $this->assertFalse($outcome->isError, $outcome->text);

        return $outcome->data;
    }

    public function testListProjectsIncludesWayneUiAsProjectZero(): void
    {
        $data = $this->data($this->call(Boot::core(), 'list_projects'));
        $ids = array_column($data['projects'], 'id');

        $this->assertSame('wayne-ui', $ids[0]);
        $this->assertContains('creed', $ids);
        $this->assertContains('advisor', $ids);
    }

    public function testReadProjectDocsPagesLongDocuments(): void
    {
        $booted = Boot::core(['ADVISOR_MAX_CHARS' => '1000']);
        $first = $this->data($this->call($booted, 'read_project_docs', ['project' => 'creed', 'doc' => 'readme']));

        $this->assertLessThanOrEqual(1000, mb_strlen($first['text']));
        $this->assertNotNull($first['next_offset']);
        $second = $this->data($this->call($booted, 'read_project_docs', ['project' => 'creed', 'doc' => 'readme', 'offset' => $first['next_offset']]));
        $this->assertNotSame($first['text'], $second['text']);
    }

    public function testReadProjectDocsServesTheWayneUiContract(): void
    {
        $data = $this->data($this->call(Boot::core(), 'read_project_docs', ['project' => 'wayne-ui', 'doc' => 'contract']));

        $this->assertNotSame('', $data['text']);
        $this->assertStringStartsWith('wayne-ui/', $data['source']);
    }

    public function testAMissingDocIsATeachingErrorNotACrash(): void
    {
        $outcome = $this->call(Boot::core(), 'read_project_docs', ['project' => 'creed', 'doc' => 'contract']);

        $this->assertTrue($outcome->isError);
        $this->assertStringContainsString('contract', $outcome->text);
    }

    public function testGetProjectStatusReportsStateAndNextStep(): void
    {
        $data = $this->data($this->call(Boot::core(), 'get_project_status', ['project' => 'proctor']));

        $this->assertSame('proctor', $data['project']);
        $this->assertNotEmpty($data['state']);
        $this->assertNotEmpty($data['next_step']);
    }

    public function testPolicyLookupUsesTheFixedDay(): void
    {
        $booted = Boot::core();
        $all = $this->data($this->call($booted, 'policy_lookup', ['component' => 'php']));
        $one = $this->data($this->call($booted, 'policy_lookup', ['component' => 'php', 'version' => '8.3']));

        $this->assertSame('2026-10-28', $all['today']);
        $this->assertNotEmpty($one);
        $this->assertTrue($this->call($booted, 'policy_lookup', ['component' => 'php', 'version' => '8.3; rm -rf'])->isError);
        $this->assertTrue($this->call($booted, 'policy_lookup', ['component' => 'cobol'])->isError);
    }

    public function testListStackRulesFiltersAndCites(): void
    {
        $data = $this->data($this->call(Boot::core(), 'list_stack_rules', ['permutation' => 'laravel-inertia-vue']));

        $this->assertNotEmpty($data['rules']);
        foreach ($data['rules'] as $rule) {
            $this->assertStringStartsWith('creed/handbook/', $rule['cite'] === '' ? '' : (str_starts_with($rule['cite'], 'creed/policy') ? 'creed/handbook/' : $rule['cite']));
            $this->assertNotSame('', $rule['rule']);
        }
    }

    public function testRunProctorCheckGoesThroughTheAllowlistedRunner(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/proctor', '<?php');
        $fake = new FakeProcessRunner(new ProcessOutput(1, (string) json_encode(['tool' => 'proctor', 'version' => '0.1.0', 'result' => 'fail', 'findings' => [['check_id' => 'php-floor', 'status' => 'fail', 'message' => 'PHP 7.4 is past end of life.']]]), ''));
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, $fake);

        $data = $this->data($this->call($booted, 'run_proctor_check', ['checks' => ['php-floor']]));

        $this->assertSame('fail', $data['result']);
        $this->assertCount(1, $fake->calls);
        $this->assertSame('check', $fake->calls[0]['argv'][2]);
    }

    public function testRunProctorCheckRefusesPathsOutsideTheRoot(): void
    {
        $root = $this->tempDir();
        $fake = new FakeProcessRunner();
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, $fake);

        $outcome = $this->call($booted, 'run_proctor_check', ['path' => '../../']);

        $this->assertTrue($outcome->isError);
        $this->assertSame([], $fake->calls);
    }

    public function testRunProctorCheckSaysSoWhenProctorIsNotInstalled(): void
    {
        $root = $this->tempDir();
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, new FakeProcessRunner());

        $outcome = $this->call($booted, 'run_proctor_check');

        $this->assertTrue($outcome->isError);
        $this->assertStringContainsString('proctor', strtolower($outcome->text));
    }

    public function testUpgradePathGoesThroughTheAllowlistedRunner(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/booster', '<?php');
        $plan = ['project' => ['path' => '/abs/app', 'app_type' => 'laravel', 'composer_json' => true, 'php' => '8.2', 'laravel' => '10', 'vue' => null, 'wayne_cas' => false], 'today' => '2026-10-28', 'policy' => '/adv/data/policy.json', 'target_php' => '8.4', 'target_laravel' => '11', 'steps' => [], 'warnings' => []];
        $fake = new FakeProcessRunner(new ProcessOutput(0, (string) json_encode($plan), ''));
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, $fake);

        $data = $this->data($this->call($booted, 'upgrade_path', ['to_php' => '8.4']));

        $this->assertSame('booster', $data['tool']);
        $this->assertCount(1, $fake->calls);
        $this->assertSame('plan', $fake->calls[0]['argv'][2]);
        $this->assertContains('--to-php=8.4', $fake->calls[0]['argv']);
    }

    public function testUpgradePathRefusesPathsOutsideTheRoot(): void
    {
        $root = $this->tempDir();
        $fake = new FakeProcessRunner();
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, $fake);

        $outcome = $this->call($booted, 'upgrade_path', ['path' => '../../']);

        $this->assertTrue($outcome->isError);
        $this->assertSame([], $fake->calls);
    }

    public function testUpgradePathSaysSoWhenBoosterIsNotConfigured(): void
    {
        $root = $this->tempDir();
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, new FakeProcessRunner());

        $outcome = $this->call($booted, 'upgrade_path');

        $this->assertTrue($outcome->isError);
        $this->assertStringContainsString('booster', strtolower($outcome->text));
    }

    public function testUpgradePathTimesOut(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/booster', '<?php');
        $fake = new FakeProcessRunner(new ProcessOutput(-1, '', 'timed out', true));
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, $fake);

        $outcome = $this->call($booted, 'upgrade_path');

        $this->assertTrue($outcome->isError);
        $this->assertStringContainsString('ADVISOR_BOOSTER_TIMEOUT', $outcome->text);
    }

    public function testHostileContentInAResultIsStrippedOfInvisibleText(): void
    {
        $booted = Boot::core();
        foreach (['list_projects', 'list_stack_rules'] as $tool) {
            $outcome = $this->call($booted, $tool);
            $this->assertSame([], \Waynestate\Advisor\Support\Invisible::find($outcome->text), $tool);
        }
    }

    public function testAuditLogRecordsHashesNotArguments(): void
    {
        $log = $this->tempDir() . '/audit.log';
        $booted = Boot::core(['ADVISOR_AUDIT_LOG' => $log]);

        $this->call($booted, 'get_project_status', ['project' => 'proctor']);

        $line = json_decode(trim((string) file_get_contents($log)), true);
        $this->assertSame('get_project_status', $line['tool']);
        $this->assertSame(hash('sha256', '{"project":"proctor"}'), $line['args_sha256']);
        $this->assertStringNotContainsString('"project"', (string) file_get_contents($log));
    }
}
