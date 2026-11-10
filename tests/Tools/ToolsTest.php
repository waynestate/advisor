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

    public function testListProjectsIncludesParityAsBuilt(): void
    {
        $data = $this->data($this->call(Boot::core(), 'list_projects'));
        $projects = array_column($data['projects'], null, 'id');

        $this->assertArrayHasKey('parity', $projects);
        $this->assertSame('Parity', $projects['parity']['name']);
        $this->assertSame('built', $projects['parity']['state'], 'Parity is a Go module, and a Go module is code.');
        $this->assertNull($projects['parity']['package'], 'Parity is a Go program, not a Composer package.');
        $this->assertStringContainsString('Citadel on your own computer', $projects['parity']['role']);
        $this->assertStringContainsString('Podman recommended', $projects['parity']['role']);
        $this->assertContains('readme', $projects['parity']['docs']);
    }

    public function testParityComesAfterSquireAndWayneUiStaysFirst(): void
    {
        $data = $this->data($this->call(Boot::core(), 'list_projects'));
        $ids = array_column($data['projects'], 'id');

        $this->assertSame('wayne-ui', $ids[0]);
        $this->assertSame(array_search('squire', $ids, true) + 1, array_search('parity', $ids, true));
    }

    public function testGetProjectStatusKnowsParity(): void
    {
        $data = $this->data($this->call(Boot::core(), 'get_project_status', ['project' => 'parity']));

        $this->assertSame('parity', $data['project']);
        $this->assertSame('built', $data['state']);
        $this->assertNull($data['php_constraint'], 'Parity needs no PHP on the computer it runs on.');
        $this->assertStringContainsString('parity up', $data['next_step']);
    }

    public function testReadProjectDocsServesParitysReadme(): void
    {
        $data = $this->data($this->call(Boot::core(), 'read_project_docs', ['project' => 'parity', 'doc' => 'readme']));

        $this->assertStringStartsWith('# Parity', $data['text']);
        $this->assertSame('parity/README.md', $data['source']);
    }

    public function testOwnerOfLocalDevelopmentIsParity(): void
    {
        foreach (['who owns local development?', 'Parity', 'run Citadel locally', 'parity kit', 'Podman'] as $question) {
            $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => $question]));

            $this->assertNotEmpty($data['matches'], $question);
            $this->assertSame('Parity', $data['matches'][0]['owner'], $question);
        }
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

    public function testReadProjectDocsServesTheWayneUiMigrationGuide(): void
    {
        $data = $this->data($this->call(Boot::core(), 'read_project_docs', ['project' => 'wayne-ui', 'doc' => 'migrating']));

        $this->assertStringStartsWith('# Upgrading from WayneUI 1.x', $data['text']);
        $this->assertSame('wayne-ui/MIGRATING.md', $data['source']);
    }

    public function testAMissingMigratingDocNamesWhatIsAvailable(): void
    {
        $outcome = $this->call(Boot::core(), 'read_project_docs', ['project' => 'proctor', 'doc' => 'migrating']);

        $this->assertTrue($outcome->isError);
        $this->assertStringContainsString('migrating', $outcome->text);
        $this->assertStringContainsString('readme, best-practices', $outcome->text);
    }

    public function testGetProjectStatusReportsStateAndNextStep(): void
    {
        $data = $this->data($this->call(Boot::core(), 'get_project_status', ['project' => 'proctor']));

        $this->assertSame('proctor', $data['project']);
        $this->assertNotEmpty($data['state']);
        $this->assertNotEmpty($data['next_step']);
    }

    public function testListProjectsShowsEachProjectsVersion(): void
    {
        $data = $this->data($this->call(Boot::core(), 'list_projects'));
        $versions = [];
        foreach ($data['projects'] as $project) {
            $this->assertArrayHasKey('version', $project, $project['id']);
            $versions[$project['id']] = $project['version'];
        }

        $this->assertSame('2.0.8', $versions['wayne-ui']);
    }

    public function testGetProjectStatusShowsTheVersion(): void
    {
        $data = $this->data($this->call(Boot::core(), 'get_project_status', ['project' => 'wayne-ui']));

        $this->assertSame('waynestate/wayne-ui', $data['package']);
        $this->assertSame('2.0.8', $data['version']);
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

    public function testPolicyLookupCarriesLatestPatch(): void
    {
        $policy = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/data/policy.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = $policy['components']['php']['versions']['8.3']['latest_patch'];
        $booted = Boot::core();
        $all = $this->data($this->call($booted, 'policy_lookup', ['component' => 'php']));
        $row = null;
        foreach ($all['versions'] as $candidate) {
            if ($candidate['version'] === '8.3') {
                $row = $candidate;
            }
        }
        $one = $this->data($this->call($booted, 'policy_lookup', ['component' => 'php', 'version' => '8.3.4']));

        $this->assertNotNull($row);
        $this->assertSame($expected, $row['latest_patch']);
        $this->assertSame($expected, $one['answer']['latest_patch']);
        $this->assertSame($policy['components']['php']['versions']['8.3']['latest_patch_checked'], $one['answer']['latest_patch_checked']);
    }

    public function testPolicyLookupAnswersEndOfLifeForAVersionOlderThanEveryListedOne(): void
    {
        $booted = Boot::core();
        $twoParts = $this->data($this->call($booted, 'policy_lookup', ['component' => 'php', 'version' => '5.6']));
        $threeParts = $this->data($this->call($booted, 'policy_lookup', ['component' => 'php', 'version' => '7.4.33']));

        foreach ([$twoParts, $threeParts] as $answer) {
            $this->assertContains($answer['answer']['state'], ['eol', 'eol_grace_expired']);
            $this->assertFalse($answer['answer']['supported']);
            $this->assertNull($answer['answer']['matched']);
            $this->assertStringContainsString('8.1', $answer['answer']['note']);
        }
        $this->assertSame($twoParts['answer']['state'], $threeParts['answer']['state']);
    }

    public function testPolicyLookupStillRefusesAVersionNewerThanEveryListedOne(): void
    {
        $outcome = $this->call(Boot::core(), 'policy_lookup', ['component' => 'php', 'version' => '9.9']);

        $this->assertTrue($outcome->isError);
        $this->assertStringContainsString('The policy has no entry for php 9.9', $outcome->text);
    }

    public function testPolicyLookupAnswersAMarkedCurrentLineAsActiveAsCreedDoes(): void
    {
        $data = $this->data($this->call(Boot::core(), 'policy_lookup', ['component' => 'vue', 'version' => '3']));

        $this->assertSame('3', $data['answer']['matched']);
        $this->assertSame('active', $data['answer']['state']);
        $this->assertTrue($data['answer']['supported']);
        $this->assertSame('3', $data['oldest_supported']);
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

    public function testListStackRulesQueryKeepsOnlyTheRulesThatHoldEveryWord(): void
    {
        $booted = Boot::core();
        $all = $this->data($this->call($booted, 'list_stack_rules'));
        $data = $this->data($this->call($booted, 'list_stack_rules', ['query' => 'session']));

        $this->assertSame('session', $data['query']);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertLessThan($all['total'], $data['total']);
        $this->assertNull($all['query']);
        foreach ($data['rules'] as $rule) {
            $this->assertMatchesRegularExpression('/(?<![a-z0-9])session/', strtolower($rule['heading'] . ' ' . $rule['rule']), $rule['id']);
            $this->assertStringStartsWith('creed/', $rule['cite']);
        }
    }

    public function testListStackRulesQueryIgnoresQuestionWordsAndAPluralS(): void
    {
        $booted = Boot::core();
        $plain = $this->data($this->call($booted, 'list_stack_rules', ['query' => 'session']));
        $asked = $this->data($this->call($booted, 'list_stack_rules', ['query' => 'What does the stack say about sessions?']));

        $this->assertSame(array_column($plain['rules'], 'id'), array_column($asked['rules'], 'id'));
    }

    public function testListStackRulesQueryNeedsEveryWordAndCombinesWithPermutation(): void
    {
        $booted = Boot::core();
        $one = $this->data($this->call($booted, 'list_stack_rules', ['query' => 'session']));
        $two = $this->data($this->call($booted, 'list_stack_rules', ['query' => 'session cookie']));
        $this->assertLessThanOrEqual($one['total'], $two['total']);
        foreach ($two['rules'] as $rule) {
            $text = strtolower($rule['heading'] . ' ' . $rule['rule']);
            $this->assertStringContainsString('session', $text);
            $this->assertStringContainsString('cookie', $text);
        }

        $laravel = $this->data($this->call($booted, 'list_stack_rules', ['query' => 'session', 'permutation' => 'laravel-inertia-vue']));
        $this->assertSame('laravel-inertia-vue', $laravel['permutation']);
        $this->assertLessThanOrEqual($one['total'], $laravel['total']);
        $this->assertSame(
            array_values(array_intersect(array_column($one['rules'], 'id'), array_column($laravel['rules'], 'id'))),
            array_column($laravel['rules'], 'id'),
        );
    }

    public function testListStackRulesQueryWithNoMatchSaysSo(): void
    {
        $data = $this->data($this->call(Boot::core(), 'list_stack_rules', ['query' => 'zzyzx quokka']));

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['rules']);
        $this->assertNull($data['next_offset']);
        $this->assertStringContainsString('fewer words', $data['note']);
    }

    public function testListStackRulesRejectsAQueryWithNothingToMatch(): void
    {
        $booted = Boot::core();
        $this->assertTrue($this->call($booted, 'list_stack_rules', ['query' => 'what is the'])->isError);
        $this->assertTrue($this->call($booted, 'list_stack_rules', ['query' => 'x'])->isError);
        $this->assertTrue($this->call($booted, 'list_stack_rules', ['query' => 'session; rm -rf'])->isError);
    }

    public function testOwnerOfFindsTheProjectThatOwnsATopic(): void
    {
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => 'composer audit']));

        $this->assertNotEmpty($data['matches']);
        $this->assertSame('PROCTOR', $data['matches'][0]['owner']);
        $this->assertArrayHasKey('role', $data['matches'][0]);
        $this->assertArrayHasKey('also', $data['matches'][0]);
        $this->assertNotSame('', $data['source']);
    }

    public function testOwnerOfNamesTheDecisionBehindTheAnswer(): void
    {
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => 'composer audit']));

        $this->assertSame('composer audit', $data['matches'][0]['topic']);
        $this->assertStringStartsWith('One owner per rule, copies are generated', $data['matches'][0]['decision']);
        foreach ($data['matches'] as $match) {
            $this->assertNotSame('', $match['decision'], $match['topic'] . ' has no decision');
        }
    }

    public function testOwnerOfIgnoresTheQuestionWords(): void
    {
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => 'Who owns composer audit?']));

        $this->assertSame('composer audit', $data['matches'][0]['topic']);
        $this->assertSame('PROCTOR', $data['matches'][0]['owner']);
    }

    /**
     * The one-owner-per-rule table, row for row: each rule answers with its owner first.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function oneOwnerPerRule(): array
    {
        return [
            'version policy' => ['who owns the version policy', 'CREED'],
            'policy.json' => ['policy.json', 'CREED'],
            'lock file' => ['composer.lock', 'PROCTOR'],
            'composer audit' => ['composer audit', 'PROCTOR'],
            'MySQL 5.6' => ['MySQL 5.6', 'PROCTOR'],
            '/up' => ['who owns /up?', 'HELMET'],
            'deploy files' => ['deploy files', 'Squire'],
            'release scripts' => ['release scripts', 'Squire'],
            'local development' => ['local development', 'Parity'],
            '.gitlab-ci.yml' => ['.gitlab-ci.yml', 'Preflight'],
            'parts list' => ['the parts list', 'ADVISOR'],
            'app wiring' => ['who owns app wiring', 'Sourdough'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('oneOwnerPerRule')]
    public function testOwnerOfAnswersEachRuleWithItsOwnerFirst(string $question, string $owner): void
    {
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => $question]));

        $this->assertNotEmpty($data['matches'], $question);
        $this->assertSame($owner, $data['matches'][0]['owner'], $question);
    }

    public function testOwnerOfMatchesWholeWordStartsOnly(): void
    {
        // "it" is a question word and "dit" starts no word, so neither reaches "audit".
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => 'dit']));

        $this->assertSame([], $data['matches']);
    }

    public function testOwnerOfMatchesCaseInsensitively(): void
    {
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => 'Composer AUDIT']));

        $this->assertNotEmpty($data['matches']);
        $this->assertSame('PROCTOR', $data['matches'][0]['owner']);
    }

    public function testOwnerOfListsKnownTopicsWhenNothingMatches(): void
    {
        $data = $this->data($this->call(Boot::core(), 'owner_of', ['topic' => 'quantum flux capacitor']));

        $this->assertSame([], $data['matches']);
        $this->assertContains('composer audit', $data['known_topics']);
        $this->assertArrayHasKey('note', $data);
    }

    public function testOwnerOfRefusesATopicWithNoWords(): void
    {
        $outcome = $this->call(Boot::core(), 'owner_of', ['topic' => '   ']);

        $this->assertTrue($outcome->isError);
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
