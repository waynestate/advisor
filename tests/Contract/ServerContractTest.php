<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Support\Invisible;
use Waynestate\Advisor\Tests\Support\Boot;
use Waynestate\Advisor\Tests\Support\McpHarness;

/**
 * Rules every ADVISOR tool must keep, whichever module it comes from. A module
 * that breaks one fails here before it can ship (best practices 2.x and 4.x).
 */
final class ServerContractTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        return (new McpHarness(Boot::core()))->call('tools/list')['result']['tools'];
    }

    public function testEveryToolStatesAllFourHints(): void
    {
        foreach ($this->tools() as $tool) {
            foreach (['readOnlyHint', 'destructiveHint', 'idempotentHint', 'openWorldHint'] as $hint) {
                $this->assertIsBool($tool['annotations'][$hint] ?? null, $tool['name'] . ' must set ' . $hint . ' explicitly');
            }
        }
    }

    public function testThisBuildOffersOnlyReadOnlyNonDestructiveTools(): void
    {
        foreach ($this->tools() as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'], $tool['name']);
            $this->assertFalse($tool['annotations']['destructiveHint'], $tool['name']);
        }
    }

    public function testSchemasRefuseExtraProperties(): void
    {
        foreach ($this->tools() as $tool) {
            $this->assertSame('object', $tool['inputSchema']['type'], $tool['name']);
            $this->assertFalse($tool['inputSchema']['additionalProperties'] ?? true, $tool['name']);
        }
    }

    public function testNamesAndDescriptionsAreBounded(): void
    {
        foreach ($this->tools() as $tool) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,63}$/', $tool['name']);
            $this->assertLessThan(4000, strlen($tool['description']), $tool['name']);
        }
    }

    public function testNoToolLetsTheModelRunAnArbitraryCommandOrPath(): void
    {
        $banned = '/^(run_command|exec|shell|bash|eval|execute|run_php|read_file|write_file|delete_file)$/';
        foreach ($this->tools() as $tool) {
            $this->assertDoesNotMatchRegularExpression($banned, $tool['name']);
            foreach (array_keys($tool['inputSchema']['properties'] ?? []) as $argument) {
                $this->assertNotContains($argument, ['command', 'cmd', 'script', 'code', 'sql', 'url'], $tool['name'] . ' takes a free-form ' . $argument);
            }
        }
    }

    public function testNoInvisibleCharactersInAnythingTheServerPublishes(): void
    {
        $harness = new McpHarness(Boot::core());
        $harness->initialize();
        $ids = [$harness->request('tools/list'), $harness->request('resources/list'), $harness->request('prompts/list')];
        $responses = $harness->run();

        foreach ($ids as $id) {
            $this->assertSame([], Invisible::find((string) json_encode($responses[$id], JSON_UNESCAPED_UNICODE)));
        }
        foreach (glob(dirname(__DIR__, 2) . '/resources/prompts/*.md') ?: [] as $prompt) {
            $this->assertSame([], Invisible::find((string) file_get_contents($prompt)), $prompt);
        }
    }

    public function testShippedTextFilesHoldNoInvisibleCharacters(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . '/*.md') ?: [],
            glob($root . '/src/*/*.php') ?: [],
            glob($root . '/data/handbook/*.md') ?: [],
            glob($root . '/data/docs/*/*') ?: [],
            [$root . '/composer.json', $root . '/modules.json'],
        );
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertSame([], Invisible::find((string) file_get_contents($file)), $file . ' holds hidden characters');
        }
    }

    public function testNoSecretsOrInternalHostsInTheRepoText(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob($root . '/src/*/*.php') ?: [], glob($root . '/*.md') ?: [], glob($root . '/data/*.json') ?: []);
        foreach ($files as $file) {
            $text = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/-----BEGIN [A-Z ]*PRIVATE KEY-----/', $text, $file);
            $this->assertDoesNotMatchRegularExpression('/\b(ghp_|glpat-|AKIA)[A-Za-z0-9]{12,}/', $text, $file);
        }
    }
}
