<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Content\Denylist;
use Waynestate\Advisor\Content\Generator;
use Waynestate\Advisor\Support\FixedClock;
use Waynestate\Advisor\Tests\Support\TempDir;

final class GeneratorTest extends TestCase
{
    use TempDir;

    private string $sources;

    private string $out;

    private string $seed;

    protected function setUp(): void
    {
        $base = $this->tempDir();
        $this->sources = $base . '/stack';
        $this->out = $base . '/data';
        $this->seed = $base . '/seed.json';

        $this->put($this->sources . '/creed/policy/policy.json', (string) json_encode([
            'schema_version' => 1,
            'components' => ['php' => ['versions' => ['8.3' => ['active_until' => '2025-12-31', 'security_until' => '2027-12-31']]]],
            'tools' => ['composer' => ['minimum' => '2.8.0']],
            'rules' => ['eol_grace_days' => 90],
        ]));
        $this->put($this->sources . '/creed/handbook/README.md', "# Handbook\n");
        $this->put($this->sources . '/creed/handbook/code.md', "# Code\r\n\r\n## Style\r\n\r\n- Laravel apps use the laravel preset.\r\n- Every repo has one formatter,\r\n  never two.\r\n- Vue components use script setup.\r\n");
        $this->put($this->sources . '/proctor/composer.json', '{"name":"waynestate/proctor","require":{"php":"^8.3"}}');
        $this->put($this->sources . '/proctor/README.md', "# PROCTOR\n\nZero\u{200B}width and tag\u{E0041} text.\n");
        $this->put($this->sources . '/wayne-ui/contract/markup-contract.json', '{"contract":true}');
        $this->put($this->sources . '/wayne-ui/package.json', '{}');
        $this->put($this->seed, (string) json_encode(['projects' => [
            ['id' => 'proctor', 'name' => 'PROCTOR', 'kind' => 'check', 'role' => 'Checks.', 'next_step' => 'Run it.'],
            ['id' => 'wayne-ui', 'name' => 'WayneUI', 'kind' => 'ui', 'role' => 'UI.', 'next_step' => 'Use it.', 'contract' => 'contract/markup-contract.json'],
            ['id' => 'later', 'name' => 'LATER', 'kind' => 'x', 'role' => 'Not yet.', 'next_step' => 'Wait.'],
        ]]));
    }

    private function generator(string $date = '2026-10-28', ?Denylist $denylist = null): Generator
    {
        return new Generator($this->sources, $this->out, $this->seed, new FixedClock($date), $denylist);
    }

    public function testBuildsAVerifiableBundle(): void
    {
        $this->generator()->generate();
        $bundle = new ContentBundle($this->out);

        $this->assertSame([], $bundle->problems());
        $this->assertSame(['proctor', 'wayne-ui', 'later'], $bundle->projectIds());
        $this->assertSame('built', $bundle->project('proctor')['state'] ?? null);
        $this->assertSame('proposed', $bundle->project('later')['state'] ?? null);
        $this->assertSame('waynestate/proctor', $bundle->project('proctor')['package'] ?? null);
        $this->assertSame('^8.3', $bundle->project('proctor')['php'] ?? null);
        $this->assertSame(['readme'], $bundle->docsFor('proctor'));
        $this->assertSame(['contract'], $bundle->docsFor('wayne-ui'));
        $this->assertSame('proctor/README.md', $bundle->source('docs/proctor/readme.md'));
    }

    public function testSourcesAreCleanedOnTheWayIn(): void
    {
        $this->generator()->generate();
        $bundle = new ContentBundle($this->out);

        $readme = (string) $bundle->doc('proctor', 'readme');
        $this->assertStringContainsString('Zerowidth and tag text.', $readme);
        $code = (string) $bundle->handbook('code');
        $this->assertStringNotContainsString("\r", $code);
        $this->assertStringEndsWith("\n", $code);
    }

    public function testExtractsCitableRulesWithTheirLines(): void
    {
        $this->generator()->generate();
        $rules = (new ContentBundle($this->out))->rules();
        $byId = array_column($rules, null, 'id');

        $this->assertSame('handbook/code.md:5', $byId['code-1']['source']);
        $this->assertSame(['laravel-blade', 'laravel-blade-vue', 'laravel-inertia-vue'], $byId['code-1']['applies_to']);
        $this->assertSame('Every repo has one formatter, never two.', $byId['code-2']['text'], 'A wrapped bullet is one rule.');
        $this->assertCount(5, $byId['code-2']['applies_to']);
        $this->assertSame(['raw-php-vue', 'laravel-blade-vue', 'laravel-inertia-vue'], $byId['code-3']['applies_to']);
        $this->assertArrayHasKey('policy-eol_grace_days', $byId);
        $this->assertArrayHasKey('policy-tool-composer', $byId);
    }

    public function testRegeneratingWithNoChangeIsByteIdenticalAndKeepsTheDate(): void
    {
        $this->generator('2026-10-28')->generate();
        $first = (string) file_get_contents($this->out . '/manifest.json');

        $changed = $this->generator('2027-02-02')->generate();

        $this->assertSame([], $changed);
        $this->assertSame($first, (string) file_get_contents($this->out . '/manifest.json'));
    }

    public function testCheckSeesDriftAndGenerateFixesIt(): void
    {
        $this->generator()->generate();
        $this->assertSame([], $this->generator()->check());

        $this->put($this->sources . '/creed/handbook/code.md', "# Code\n\n- A new rule.\n");
        $diff = $this->generator('2026-11-01')->check();

        $this->assertContains('changed: handbook/code.md', $diff);
        $this->assertContains('changed: rules.json', $diff);

        $this->generator('2026-11-01')->generate();
        $this->assertSame([], $this->generator()->check());
        $this->assertSame('2026-11-01', (new ContentBundle($this->out))->generatedOn());
    }

    public function testCheckSeesAHandEditedFile(): void
    {
        $this->generator()->generate();
        file_put_contents($this->out . '/policy.json', '{"schema_version":1}');

        $this->assertContains('changed: policy.json', $this->generator()->check());
    }

    public function testCheckSeesARemovedSource(): void
    {
        $this->generator()->generate();
        unlink($this->sources . '/proctor/README.md');

        $this->assertContains('removed: docs/proctor/readme.md', $this->generator()->check());
    }

    public function testInternalNamesAreReplacedWithNeutralWordingOnTheWayIn(): void
    {
        $denylistFile = $this->tempDir() . '/denylist.txt';
        file_put_contents($denylistFile, "someone@example.edu\nci.internal.example\n");
        $denylist = Denylist::fromFile($denylistFile);

        $this->put($this->sources . '/proctor/README.md', "# PROCTOR\n\nSee `design/case-note.md` and [ADR-9999 (metrics speak Prometheus)](../design/ADR/ADR-9999-metrics-speak-prometheus.md).\nQuestions go to someone@example.edu in the coordination repository.\nThe CI runner is at ci.internal.example.\n");
        $this->generator('2026-10-28', $denylist)->generate();
        $readme = (string) (new ContentBundle($this->out))->doc('proctor', 'readme');

        $this->assertStringNotContainsString('design/ADR', $readme);
        $this->assertStringNotContainsString('design/case-note', $readme);
        $this->assertStringNotContainsString('example.edu', $readme);
        $this->assertStringNotContainsString('coordination repo', $readme);
        $this->assertStringNotContainsString('ci.internal.example', $readme);
        $this->assertStringNotContainsString('someone@example.edu', $readme);
        $this->assertStringContainsString('a design document', $readme);
        $this->assertStringContainsString('an ADR', $readme);
        $this->assertStringContainsString("the stack's design documents", $readme);
    }

    public function testMissingSourcesAreAnErrorNotAnEmptyBundle(): void
    {
        $this->expectException(RuntimeException::class);
        (new Generator($this->sources . '/nope', $this->out, $this->seed, new FixedClock('2026-10-28')))->build();
    }

    public function testRefusesAPolicySchemaItDoesNotKnow(): void
    {
        $this->put($this->sources . '/creed/policy/policy.json', '{"schema_version":2}');

        $this->expectException(\InvalidArgumentException::class);
        $this->generator()->build();
    }
}
