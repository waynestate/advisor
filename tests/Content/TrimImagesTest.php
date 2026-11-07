<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Content\Generator;
use Waynestate\Advisor\Support\FixedClock;
use Waynestate\Advisor\Tests\Support\TempDir;

/** Image boilerplate in bundled docs is trimmed on the way in. */
final class TrimImagesTest extends TestCase
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
        $this->put($this->sources . '/proctor/composer.json', '{"name":"waynestate/proctor","require":{"php":"^8.3"}}');
        $this->put($this->sources . '/wayne-ui/contract/markup-contract.json', '{"contract":true}');
        $this->put($this->sources . '/wayne-ui/package.json', '{}');
        $this->put($this->seed, (string) json_encode(['projects' => [
            ['id' => 'proctor', 'name' => 'PROCTOR', 'kind' => 'check', 'tagline' => 'checks an app', 'role' => 'Checks.', 'next_step' => 'Run it.'],
            ['id' => 'wayne-ui', 'name' => 'WayneUI', 'kind' => 'ui', 'tagline' => 'interface', 'role' => 'UI.', 'next_step' => 'Use it.', 'contract' => 'contract/markup-contract.json'],
        ]]));
        $this->put($base . '/ownership.json', (string) json_encode(['topics' => [
            ['topic' => 'checks', 'owner' => 'PROCTOR', 'role' => 'Runs the checks.', 'also' => [], 'decision' => 'A test decision'],
        ]]));
    }

    private function generator(): Generator
    {
        return new Generator($this->sources, $this->out, $this->seed, new FixedClock('2026-10-28'));
    }

    public function testCentredBlocksBadgesAndGifLinesAreTrimmedButProseSurvives(): void
    {
        $this->put($this->sources . '/proctor/README.md', <<<MD
# PROCTOR

<div align="center">
    <img src="docs/hero.png" alt="PROCTOR hero" width="600">
</div>

<div align="center">
    <a href="https://ci.example"><img src="badge.svg" alt="CI"></a>
    <a href="https://pkg.example"><img src="pkg.svg" alt="Package"></a>
</div>

![demo](docs/demo.gif)

Check an app against the stack.

MD);
        $this->generator()->generate();
        $readme = (string) (new ContentBundle($this->out))->doc('proctor', 'readme');

        $this->assertStringContainsString('# PROCTOR', $readme);
        $this->assertStringContainsString('Check an app against the stack.', $readme);
        $this->assertStringNotContainsString('<img', $readme);
        $this->assertStringNotContainsString('align="center"', $readme);
        $this->assertStringNotContainsString('demo.gif', $readme);
        $this->assertStringNotContainsString("\n\n\n", $readme);
    }

    public function testAnImgInsideAFencedCodeBlockSurvives(): void
    {
        $this->put($this->sources . '/proctor/README.md', <<<MD
# PROCTOR

```html
<img src="docs/example.png" alt="an example of markup, not a real image">
```

Prose after the fence.

MD);
        $this->generator()->generate();
        $readme = (string) (new ContentBundle($this->out))->doc('proctor', 'readme');

        $this->assertStringContainsString("```html\n<img src=\"docs/example.png\" alt=\"an example of markup, not a real image\">\n```\n", $readme);
        $this->assertStringContainsString('Prose after the fence.', $readme);
    }
}
