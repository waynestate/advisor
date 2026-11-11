<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Site;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Security\ProcessOutput;
use Waynestate\Advisor\Security\ProcessRunner;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Server\Runtime;
use Waynestate\Advisor\Support\FixedClock;
use Waynestate\Advisor\Tests\Support\AssertsOutputSchema;
use Waynestate\Advisor\Tests\Support\Boot;
use Waynestate\Advisor\Tests\Support\FakeProcessRunner;
use Waynestate\Advisor\Tests\Support\TempDir;

/**
 * The front page's "Ask the map" card has a field book: for each tool call on
 * a route, what the real tool handed back. Those readouts are recorded here,
 * from the real tools on the bundled data (PROCTOR's report is a fixture, since
 * the page has no app to check), so the page can never show an answer shape
 * the server does not give. A tool that changes its answer, or a route that
 * gains a call, fails this test until the file is regenerated:
 *
 *     ADVISOR_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit tests/Site/ReadoutsTest.php
 *
 * Like a surveyor's field book, it is copied fair, abridged, and dated.
 */
final class ReadoutsTest extends TestCase
{
    use AssertsOutputSchema;
    use TempDir;

    private const FILE = 'site/assets/readouts.json';

    private const MAX_STRING = 140;

    private const MAX_ITEMS = 3;

    /** Arguments the tools take as a list, even when the route names one value. */
    private const LIST_ARGUMENTS = ['checks'];

    /** policy_lookup's component follows the question on the page, so every one it can pick is recorded. */
    private const DYNAMIC_CALLS = [
        'policy_lookup component: php',
        'policy_lookup component: laravel',
        'policy_lookup component: node',
        'policy_lookup component: mysql',
    ];

    public function testTheFrontPageReadoutsAreTheToolsOwnAnswers(): void
    {
        $root = dirname(__DIR__, 2);
        $expected = $this->render($this->readouts($this->callsOnThePage($root)));
        $file = $root . '/' . self::FILE;

        if (getenv('ADVISOR_UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($file, $expected);
        }

        $this->assertFileExists($file, 'Run with ADVISOR_UPDATE_SNAPSHOTS=1 to record the front page readouts.');

        // The words move with every resync of the bundled docs, and that is fine:
        // the page says when they were recorded. What must not move is the shape,
        // the calls and the field names, because that is what the page teaches.
        $recorded = json_decode((string) file_get_contents($file), true);
        $current = json_decode($expected, true);
        $this->assertIsArray($recorded, self::FILE . ' is not JSON.');
        $this->assertSame(array_keys($current['calls']), array_keys($recorded['calls'] ?? []), 'The map\'s calls changed. ' . self::regenerate());
        foreach ($current['calls'] as $call => $readout) {
            $this->assertSame($readout['tool'], $recorded['calls'][$call]['tool'], $call);
            $this->assertSame($readout['arguments'], $recorded['calls'][$call]['arguments'], $call);
            $this->assertTrue(
                self::sameShape(self::shape($readout['result']), self::shape($recorded['calls'][$call]['result'])),
                $call . ' answers in a different shape now. ' . self::regenerate(),
            );
        }
    }

    /** Equal shapes, except that an empty list (or a list of plain values) matches a list of anything. */
    private static function sameShape(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b) && array_keys($a) === ['list'] && array_keys($b) === ['list']) {
            return $a['list'] === 'value' || $b['list'] === 'value' || self::sameShape($a['list'], $b['list']);
        }
        if (!is_array($a) || !is_array($b)) {
            return $a === $b;
        }
        if (array_keys($a) !== array_keys($b)) {
            return false;
        }
        foreach ($a as $key => $value) {
            if (!self::sameShape($value, $b[$key])) {
                return false;
            }
        }

        return true;
    }

    private static function regenerate(): string
    {
        return 'Review the change, then run ADVISOR_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit tests/Site/ReadoutsTest.php and commit ' . self::FILE . '.';
    }

    /**
     * Field names all the way down; a list is described by its first item.
     */
    private static function shape(mixed $value): mixed
    {
        if (!is_array($value)) {
            return 'value';
        }
        if (array_is_list($value)) {
            return ['list' => $value === [] || !is_array($value[0]) ? 'value' : self::shape($value[0])];
        }
        $shape = array_map(self::shape(...), $value);
        ksort($shape);

        return $shape;
    }

    public function testEveryCallOnTheMapHasAReadout(): void
    {
        $calls = $this->callsOnThePage(dirname(__DIR__, 2));

        $this->assertContains('policy_lookup component: php', $calls);
        $this->assertContains('run_proctor_check path: .', $calls);
        $this->assertContains('list_projects', $calls);
        $this->assertGreaterThanOrEqual(15, count($calls), 'The map plots at least fifteen distinct calls; did the route table move out of advisor.js?');
    }

    public function testArgumentsAreReadTheWayTheCardPrintsThem(): void
    {
        $this->assertSame([], self::arguments(''));
        $this->assertSame(['project' => 'wayne-ui', 'doc' => 'contract'], self::arguments('project: wayne-ui, doc: contract'));
        $this->assertSame(['checks' => ['php-floor', 'unbounded-constraints']], self::arguments('checks: php-floor, unbounded-constraints'));
        $this->assertSame(['checks' => ['php-floor']], self::arguments('checks: php-floor'));
        $this->assertSame(['path' => '.'], self::arguments('path: .'));
    }

    public function testAbridgingKeepsTheShapeAndSaysWhatWasLeftOut(): void
    {
        $long = str_repeat('a', 200);
        $out = self::abridge(['text' => $long, 'list' => [1, 2, 3, 4, 5], 'map' => ['k' => 'v'], 'n' => null, 'ok' => true]);

        $this->assertSame(self::MAX_STRING, mb_strlen($out['text']));
        $this->assertStringEndsWith('…', $out['text']);
        $this->assertSame([1, 2, 3, '(2 more)'], $out['list']);
        $this->assertSame(['k' => 'v'], $out['map']);
        $this->assertNull($out['n']);
        $this->assertTrue($out['ok']);
    }

    /**
     * Every `{ tool: "...", args: "..." }` step written out in the page's route
     * table, as the card prints it: the tool, then its arguments.
     *
     * @return list<string>
     */
    private function callsOnThePage(string $root): array
    {
        $js = (string) file_get_contents($root . '/site/assets/advisor.js');
        preg_match_all('/\{ tool: "([a-z_]+)"(?:, args: "([^"]*)")? \}/', $js, $matches, PREG_SET_ORDER);

        $calls = self::DYNAMIC_CALLS;
        foreach ($matches as $match) {
            $calls[] = trim($match[1] . ' ' . ($match[2] ?? ''));
        }
        $calls = array_values(array_unique($calls));
        sort($calls);

        return $calls;
    }

    /**
     * @param list<string> $calls
     *
     * @return array<string, array<string, mixed>>
     */
    private function readouts(array $calls): array
    {
        $readouts = [];
        foreach ($calls as $call) {
            [$tool, $args] = array_pad(explode(' ', $call, 2), 2, '');
            $booted = $tool === 'run_proctor_check' ? $this->withProctor() : self::boot();
            $definition = $booted->catalog->tools()[$tool] ?? null;
            $this->assertNotNull($definition, 'The map calls ' . $tool . ', which ADVISOR does not offer.');

            $outcome = $booted->invoker->invoke($definition, self::arguments($args));
            $this->assertFalse($outcome->isError, 'The map calls ' . $call . ' and the tool refuses it: ' . $outcome->text);
            $this->assertConformsToOutputSchema($definition, $outcome);

            $readouts[$call] = [
                'tool' => $tool,
                'arguments' => self::arguments($args) === [] ? new \stdClass() : self::arguments($args),
                'sample' => $tool === 'run_proctor_check',
                'result' => self::abridge($outcome->data),
            ];
        }

        return $readouts;
    }

    /** PROCTOR runs inside the developer's app; for the page, a small report stands in for one. */
    private function withProctor(): Booted
    {
        $root = $this->tempDir();
        mkdir($root . '/vendor/bin', 0777, true);
        file_put_contents($root . '/vendor/bin/proctor', '<?php');
        $report = [
            'tool' => 'proctor',
            'version' => '0.15.0',
            'result' => 'fail',
            'findings' => [
                ['check_id' => 'php-floor', 'status' => 'fail', 'message' => 'composer.json allows PHP 8.1, which is past end of life. Raise the constraint to ^8.2 or newer.', 'path' => 'composer.json', 'line' => 9],
                ['check_id' => 'unbounded-constraints', 'status' => 'pass', 'message' => 'Every constraint has an upper bound.'],
                ['check_id' => 'lock-committed', 'status' => 'pass', 'message' => 'composer.lock is committed.'],
                ['check_id' => 'portability', 'status' => 'pass', 'message' => 'No platform-specific paths or commands found.'],
            ],
        ];

        return self::boot(['ADVISOR_PROJECT_ROOT' => $root], $root, new FakeProcessRunner(new ProcessOutput(1, (string) json_encode($report), '')));
    }

    /**
     * Booted like Boot::core(), but on the day the bundle was generated, so the
     * readouts' "today" and the bundle's own date agree on the page.
     *
     * @param array<string, string> $env
     */
    private static function boot(array $env = [], ?string $root = null, ?ProcessRunner $processes = null): Booted
    {
        $config = Config::fromEnv(['ADVISOR_ENABLED' => '1', ...$env], $root ?? dirname(__DIR__, 2));

        return Runtime::boot($config, new NullLogger(), $processes ?? new FakeProcessRunner(), new FixedClock(self::day()));
    }

    /** The bundle's generated_on, from its manifest. */
    private static function day(): string
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/data/manifest.json'), true);
        $day = is_array($manifest) ? ($manifest['generated_on'] ?? null) : null;

        return is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : Boot::TODAY;
    }

    /**
     * `project: wayne-ui, doc: contract` becomes ['project' => 'wayne-ui', 'doc' => 'contract'];
     * a part with no colon is one more value of the argument before it.
     *
     * @return array<string, mixed>
     */
    private static function arguments(string $printed): array
    {
        $args = [];
        $last = null;
        foreach ($printed === '' ? [] : explode(', ', $printed) as $part) {
            if (preg_match('/^([a-z_]+): (.*)$/', $part, $m)) {
                $last = $m[1];
                $args[$last] = in_array($last, self::LIST_ARGUMENTS, true) ? [$m[2]] : $m[2];
            } elseif ($last !== null) {
                $args[$last] = [...(array) $args[$last], $part];
            }
        }

        return $args;
    }

    private static function abridge(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = (string) preg_replace('/\s+/', ' ', $value);

            return mb_strlen($value) > self::MAX_STRING ? rtrim(mb_substr($value, 0, self::MAX_STRING - 1)) . '…' : $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            return array_map(self::abridge(...), $value);
        }
        $kept = array_map(self::abridge(...), array_slice($value, 0, self::MAX_ITEMS));
        if (count($value) > self::MAX_ITEMS) {
            $kept[] = '(' . (count($value) - self::MAX_ITEMS) . ' more)';
        }

        return $kept;
    }

    /**
     * @param array<string, array<string, mixed>> $readouts
     */
    private function render(array $readouts): string
    {
        $file = [
            'about' => 'What each tool call on the front page map hands back, recorded by tests/Site/ReadoutsTest.php from the real tools on the bundled data and abridged (long text cut, lists cut to three). Run PROCTOR yourself for your own app\'s report: the run_proctor_check readouts use a sample report.',
            'as_of' => self::day(),
            'calls' => $readouts,
        ];

        return json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
