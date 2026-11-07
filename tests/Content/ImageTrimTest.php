<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Content;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Content\ContentBundle;
use Waynestate\Advisor\Content\ImageTrim;

/** The image trim on its own, and on the way out of the bundle. */
final class ImageTrimTest extends TestCase
{
    public function testALinkedHeroAndABadgeRowAreDropped(): void
    {
        $text = <<<'MD'
            # Sourdough

            [![Sourdough: dough in a jar](docs/readme/hero.jpg)](site/index.html)

            ![php](docs/readme/badges/php.svg) ![license](docs/readme/badges/license.svg) [![GitHub CI](https://github.com/waynestate/sourdough/actions/workflows/ci.yml/badge.svg)](https://github.com/waynestate/sourdough/actions/workflows/ci.yml)

            Starts every app shape.

            MD;

        $out = ImageTrim::trim($text);

        $this->assertStringNotContainsString('hero.jpg', $out);
        $this->assertStringNotContainsString('badge', $out);
        $this->assertStringContainsString('# Sourdough', $out);
        $this->assertStringContainsString('Starts every app shape.', $out);
    }

    public function testALoneAbsoluteDiagramAndInlineImagesSurvive(): void
    {
        $text = "# Map\n\n![route](https://example.edu/route.png)\n\nSee ![icon](icon.png) in the legend.\n";

        $out = ImageTrim::trim($text);

        $this->assertStringContainsString('![route](https://example.edu/route.png)', $out);
        $this->assertStringContainsString('See ![icon](icon.png) in the legend.', $out);
    }

    public function testAGifAndACastBlockAreDroppedButCodeIsNot(): void
    {
        $text = "# Cast\n\n<div align=\"center\">\n  <a href=\"https://git.wayne.edu/x\"><img src=\"docs/readme/cast/x.png\" alt=\"X\"></a>\n</div>\n\n[![creed](https://media.example/creed.gif)](https://example.edu/gif)\n\n```html\n<img src=\"keep.png\">\n![keep](keep.gif)\n```\n";

        $out = ImageTrim::trim($text);

        $this->assertStringNotContainsString('cast/x.png', $out);
        $this->assertStringNotContainsString('creed.gif', $out);
        $this->assertStringContainsString("<img src=\"keep.png\">\n![keep](keep.gif)", $out);
    }

    public function testJsonPassesThroughAndTrimmingTwiceChangesNothing(): void
    {
        $json = '[{"img":"![x](y.png)"}]';
        $this->assertSame($json, ImageTrim::trim($json));

        $text = "# A\n\n![x](x.png)\n\nWords.\n";
        $once = ImageTrim::trim($text);
        $this->assertSame($once, ImageTrim::trim($once));
    }

    public function testTheBundleServesDocsTrimmedWithoutTouchingTheFileOnDisk(): void
    {
        $bundle = new ContentBundle(Advisor::dataDir());
        $this->assertSame([], $bundle->problems());

        foreach ($bundle->projectIds() as $project) {
            foreach ($bundle->docsFor($project) as $doc) {
                if ($doc === 'contract') {
                    continue;
                }
                $served = (string) $bundle->doc($project, $doc);
                $this->assertDoesNotMatchRegularExpression('/^[ \t]*\[?!\[[^\]]*\]\((?!http)[^)]*\)\]?(?:\([^)]*\))?[ \t]*$/m', self::outsideFences($served), "{$project}/{$doc} serves a relative image line");
                $this->assertDoesNotMatchRegularExpression('/^[ \t]*(?:<a\s[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>)?[ \t]*$/m', self::outsideFences($served), "{$project}/{$doc} serves a lone img tag");
            }
        }
    }

    private static function outsideFences(string $text): string
    {
        return (string) preg_replace('/^(```|~~~).*?^\1[^\n]*$/ms', '', $text);
    }
}
