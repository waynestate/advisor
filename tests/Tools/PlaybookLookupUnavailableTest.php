<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Tools;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Tests\Support\Boot;

/**
 * playbook_lookup without the private package: a teaching answer, not an error.
 * Runs in its own process because PlaybookLookupTest loads the Playbook stub
 * for the rest of the suite's main process.
 */
final class PlaybookLookupUnavailableTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testMissingPlaybookSaysHowToGetItInsteadOfFailing(): void
    {
        $this->assertFalse(
            class_exists(\Waynestate\Playbook\Playbook::class, false),
            'the PLAYBOOK stub must not be loaded in this process',
        );
        $booted = Boot::core();
        $tool = $booted->catalog->tools()['playbook_lookup'];

        foreach ([[], ['key' => 'CAS_URL']] as $args) {
            $outcome = $booted->invoker->invoke($tool, $args);
            $this->assertFalse($outcome->isError, $outcome->text);
            $this->assertFalse($outcome->data['available']);
            $this->assertStringContainsString('composer require waynestate/playbook', $outcome->data['how_to_get']);
            $this->assertStringContainsString('https://git.wayne.edu/enterprise-apps/playbook.git', $outcome->data['how_to_get']);
            $this->assertStringContainsString('PLAYBOOK is private on git.wayne.edu.', $outcome->data['how_to_get']);
        }
    }
}
