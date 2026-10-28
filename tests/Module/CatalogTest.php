<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Module;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Module\CoreModule;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Advisor\Tests\Fixtures\FixtureModule;
use Waynestate\Advisor\Tests\Support\Boot;
use Waynestate\Advisor\Tests\Support\TempDir;

final class CatalogTest extends TestCase
{
    use TempDir;

    private function manifest(): string
    {
        $file = $this->tempDir() . '/modules.json';
        file_put_contents($file, (string) json_encode(['modules' => [CoreModule::class, FixtureModule::class]]));

        return $file;
    }

    /**
     * @return list<string>
     */
    private function toolNames(array $env): array
    {
        return array_keys(Boot::core($env, null, null, $this->manifest())->catalog->tools());
    }

    public function testReadOnlyIsOnByDefaultAndKeepsWriteToolsOut(): void
    {
        $names = $this->toolNames([]);

        $this->assertContains('fixture_greet', $names);
        $this->assertNotContains('fixture_write', $names);
    }

    public function testWriteToolsNeedReadOnlyExplicitlyTurnedOff(): void
    {
        $this->assertContains('fixture_write', $this->toolNames(['ADVISOR_READ_ONLY' => 'false']));
    }

    public function testReadOnlyWinsOverGroups(): void
    {
        $names = $this->toolNames(['ADVISOR_GROUPS' => 'fixture']);

        $this->assertSame(['fixture_greet'], $names);
    }

    public function testGroupsNarrowTheList(): void
    {
        $this->assertContains('policy_lookup', $this->toolNames(['ADVISOR_GROUPS' => 'stack']));
        $this->assertNotContains('run_proctor_check', $this->toolNames(['ADVISOR_GROUPS' => 'stack']));
        $this->assertSame(['run_proctor_check'], $this->toolNames(['ADVISOR_GROUPS' => 'proctor']));
    }

    public function testToolsComeBackInTheSameOrderEveryTime(): void
    {
        $names = $this->toolNames([]);
        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names);
    }

    public function testToolDefinitionsRefuseUnsafeMetadata(): void
    {
        $make = static fn(string $name, string $description, array $schema): ToolDefinition => new ToolDefinition($name, 'T', $description, $schema, static fn(): array => []);
        $object = ['type' => 'object', 'additionalProperties' => false];

        $this->assertInstanceOf(ToolDefinition::class, $make('ok_tool', 'Fine.', $object));

        foreach ([
            'bad name' => static fn() => $make('Run-Command', 'x', $object),
            'long name' => static fn() => $make(str_repeat('a', 65), 'x', $object),
            'no description' => static fn() => $make('a_tool', '', $object),
            'huge description' => static fn() => $make('a_tool', str_repeat('x', 1300), $object),
            'open schema' => static fn() => $make('a_tool', 'x', ['type' => 'object']),
            'hidden text' => static fn() => $make('a_tool', "Reads files.\u{E0049}\u{E0047}", $object),
        ] as $label => $attempt) {
            try {
                $attempt();
                $this->fail($label . ' should have been refused');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
