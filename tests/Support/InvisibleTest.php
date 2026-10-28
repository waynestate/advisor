<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Support\Invisible;

final class InvisibleTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function hidden(): array
    {
        return [
            'tag block' => ["ok\u{E0041}\u{E0042}", 'U+E0041'],
            'zero width space' => ["a\u{200B}b", 'U+200B'],
            'right to left override' => ["a\u{202E}b", 'U+202E'],
            'byte order mark' => ["\u{FEFF}text", 'U+FEFF'],
            'variation selector' => ["a\u{FE0F}", 'U+FE0F'],
            'escape' => ["a\x1b[31mred", 'U+001B'],
            'null' => ["a\0b", 'U+0000'],
        ];
    }

    #[DataProvider('hidden')]
    public function testFindsHiddenCharacters(string $text, string $label): void
    {
        $this->assertContains($label, Invisible::find($text));
        $this->assertSame([], Invisible::find(Invisible::strip($text)));
    }

    public function testKeepsPlainTextTabsAndNewlines(): void
    {
        $text = "Line one\n\tIndented \u{00E9}\u{4E2D}\u{1F600}\r\n";

        $this->assertSame([], Invisible::find($text));
        $this->assertSame($text, Invisible::strip($text));
    }

    public function testStripRemovesOnlyTheHiddenPart(): void
    {
        $this->assertSame('ignore this', Invisible::strip("ig\u{200B}nore\u{E0049} this"));
    }

    public function testInvalidUtf8BecomesValidText(): void
    {
        $out = Invisible::strip("bad\xC3\x28byte");

        $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
    }
}
