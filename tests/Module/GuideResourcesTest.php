<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Module;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Tests\Support\Boot;

final class GuideResourcesTest extends TestCase
{
    public function testServesTheOracleGuideAsAResource(): void
    {
        $resources = Boot::core()->catalog->resources();

        $this->assertArrayHasKey('advisor://guide/connect-to-oracle-on-citadel', $resources);
        $resource = $resources['advisor://guide/connect-to-oracle-on-citadel'];
        $this->assertSame('Connect to Oracle on Citadel', $resource->title);
        $this->assertSame('A hand-written ADVISOR guide', $resource->description);
        $this->assertSame('text/markdown', $resource->mimeType);

        $text = ($resource->read)();
        $this->assertStringContainsString('# Connect to Oracle on Citadel', $text);
        $this->assertStringContainsString('ORACLE_USERNAME', $text);
        foreach (['snu.gs', 'nougat', 'prod.wayne'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text);
        }
    }
}
