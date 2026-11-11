<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Security\ProcessOutput;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Tests\Support\AssertsOutputSchema;
use Waynestate\Advisor\Tests\Support\Boot;
use Waynestate\Advisor\Tests\Support\FakeProcessRunner;
use Waynestate\Advisor\Tests\Support\McpHarness;
use Waynestate\Advisor\Tests\Support\TempDir;

/**
 * Best practice 2.8: every tool returns structured content with an output
 * schema, and the content matches it. A client (or a model) can then read a
 * field without guessing, and a handler that grows a field has to say so in
 * the reviewed tools/list snapshot.
 */
final class OutputSchemaTest extends TestCase
{
    use AssertsOutputSchema;
    use TempDir;

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedTools(): array
    {
        return (new McpHarness(Boot::core()))->call('tools/list')['result']['tools'];
    }

    public function testEveryToolPublishesARealOutputSchema(): void
    {
        $tools = $this->publishedTools();
        $this->assertCount(9, $tools);

        foreach ($tools as $tool) {
            $name = $tool['name'];
            $schema = $tool['outputSchema'] ?? null;
            $this->assertIsArray($schema, $name . ' has no outputSchema');
            $this->assertSame('object', $schema['type'] ?? null, $name . ': the output schema must describe an object');
            $this->assertFalse($schema['additionalProperties'] ?? true, $name . ': the output schema must set additionalProperties false, so a new field is a reviewed change');
            $this->assertIsArray($schema['properties'] ?? null, $name . ': the output schema names no properties');
            $this->assertNotEmpty($schema['properties'], $name . ': the output schema names no properties');
            $this->assertIsArray($schema['required'] ?? null, $name . ': the output schema requires nothing');
            $this->assertNotEmpty($schema['required'], $name . ': the output schema requires nothing');

            foreach ($schema['required'] as $key) {
                $this->assertArrayHasKey($key, $schema['properties'], $name . ' requires ' . $key . ' but does not describe it');
            }
            foreach ($schema['properties'] as $key => $property) {
                $this->assertIsArray($property, $name . '.' . $key);
                $this->assertTrue(isset($property['type']) || isset($property['enum']) || isset($property['const']) || isset($property['anyOf']) || isset($property['oneOf']), $name . '.' . $key . ' has no type');
                $this->assertIsString($property['description'] ?? null, $name . '.' . $key . ' needs a description');
            }
        }
    }

    /**
     * One call per answer shape: every tool, and each branch of the tools
     * whose answer changes with their arguments.
     *
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function calls(): iterable
    {
        yield 'list_projects' => ['list_projects', []];
        yield 'get_project_status, a package' => ['get_project_status', ['project' => 'proctor']];
        yield 'get_project_status, WayneUI' => ['get_project_status', ['project' => 'wayne-ui']];
        yield 'get_project_status, a Go program' => ['get_project_status', ['project' => 'parity']];
        yield 'read_project_docs, first page' => ['read_project_docs', ['project' => 'creed', 'doc' => 'readme']];
        yield 'read_project_docs, a later page' => ['read_project_docs', ['project' => 'creed', 'doc' => 'readme', 'offset' => 4000]];
        yield 'read_project_docs, WayneUI contract' => ['read_project_docs', ['project' => 'wayne-ui', 'doc' => 'contract']];
        yield 'policy_lookup, every php line' => ['policy_lookup', ['component' => 'php']];
        yield 'policy_lookup, one php line' => ['policy_lookup', ['component' => 'php', 'version' => '8.3.4']];
        yield 'policy_lookup, older than every line' => ['policy_lookup', ['component' => 'php', 'version' => '5.6']];
        yield 'policy_lookup, a framework' => ['policy_lookup', ['component' => 'vue', 'version' => '3']];
        yield 'policy_lookup, a tool' => ['policy_lookup', ['component' => 'composer']];
        yield 'list_stack_rules, all' => ['list_stack_rules', []];
        yield 'list_stack_rules, a permutation' => ['list_stack_rules', ['permutation' => 'laravel-inertia-vue']];
        yield 'list_stack_rules, a query' => ['list_stack_rules', ['query' => 'session cookie']];
        yield 'list_stack_rules, no match' => ['list_stack_rules', ['query' => 'zzyzx quokka']];
        yield 'owner_of, a match' => ['owner_of', ['topic' => 'Who owns composer audit?']];
        yield 'owner_of, no match' => ['owner_of', ['topic' => 'quantum flux capacitor']];
    }

    /**
     * @param array<string, mixed> $args
     */
    #[DataProvider('calls')]
    public function testStructuredContentMatchesTheSchema(string $tool, array $args): void
    {
        $this->assertCallConforms(Boot::core(), $tool, $args);
    }

    public function testPlaybookAnswersMatchTheSchema(): void
    {
        require_once dirname(__DIR__) . '/Fixtures/playbook-stub.php';
        $booted = Boot::core();

        $this->assertCallConforms($booted, 'playbook_lookup', []);
        $this->assertCallConforms($booted, 'playbook_lookup', ['key' => 'CAS_URL']);
        $this->assertCallConforms($booted, 'playbook_lookup', ['key' => 'NOT_IN_PLAYBOOK']);
    }

    #[RunInSeparateProcess]
    public function testPlaybookMissingAnswerMatchesTheSchema(): void
    {
        $this->assertFalse(class_exists(\Waynestate\Playbook\Playbook::class, false), 'the PLAYBOOK stub must not be loaded in this process');

        $this->assertCallConforms(Boot::core(), 'playbook_lookup', []);
    }

    public function testProctorReportMatchesTheSchema(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/proctor', '<?php');
        $report = ['tool' => 'proctor', 'version' => '0.15.0', 'result' => 'fail', 'findings' => [
            ['check_id' => 'php-floor', 'status' => 'fail', 'message' => 'PHP 7.4 is past end of life.', 'path' => 'composer.json', 'line' => 12],
            ['check_id' => 'lock-committed', 'status' => 'pass', 'message' => 'composer.lock is committed.'],
        ]];
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, new FakeProcessRunner(new ProcessOutput(1, (string) json_encode($report), '')));

        $this->assertCallConforms($booted, 'run_proctor_check', []);
        $this->assertCallConforms($booted, 'run_proctor_check', ['checks' => ['php-floor']]);
    }

    public function testBoosterPlanMatchesTheSchema(): void
    {
        $root = $this->tempDir();
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/booster', '<?php');
        $plan = [
            'project' => ['path' => '/abs/app', 'app_type' => 'laravel', 'composer_json' => true, 'php' => '8.2', 'laravel' => '10', 'vue' => null, 'wayne_cas' => false],
            'today' => '2026-10-28',
            'policy' => '/adv/data/policy.json',
            'target_php' => '8.4',
            'target_laravel' => '11',
            'steps' => [
                ['kind' => 'php', 'from' => '8.2', 'to' => '8.3', 'notes' => ['Run the tests.']],
                ['kind' => 'laravel', 'from' => '10', 'to' => '11', 'notes' => []],
            ],
            'warnings' => ['Laravel 10 is past security support.'],
        ];
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, new FakeProcessRunner(new ProcessOutput(0, (string) json_encode($plan), '')));

        $this->assertCallConforms($booted, 'upgrade_path', ['to_php' => '8.4']);

        $empty = ['project' => [], 'today' => '2026-10-28', 'policy' => '/adv/data/policy.json', 'target_php' => null, 'target_laravel' => null, 'steps' => [], 'warnings' => []];
        $booted = Boot::core(['ADVISOR_PROJECT_ROOT' => $root], $root, new FakeProcessRunner(new ProcessOutput(0, (string) json_encode($empty), '')));

        $this->assertCallConforms($booted, 'upgrade_path', []);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function assertCallConforms(Booted $booted, string $name, array $args): void
    {
        $tool = $booted->catalog->tools()[$name];
        $outcome = $booted->invoker->invoke($tool, $args);

        $this->assertFalse($outcome->isError, $name . ': ' . $outcome->text);
        $this->assertConformsToOutputSchema($tool, $outcome);
    }
}
