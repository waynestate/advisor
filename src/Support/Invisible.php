<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Support;

/**
 * Finds and strips characters a reviewer cannot see but a model can read
 * (best practice 2.10): the TAG block, zero-width characters, bidirectional
 * controls and other non-printing code points.
 */
final class Invisible
{
    /** Code point ranges that never belong in text the model reads. */
    private const RANGES = [
        [0x00AD, 0x00AD],     // soft hyphen
        [0x034F, 0x034F],     // combining grapheme joiner
        [0x061C, 0x061C],     // Arabic letter mark
        [0x115F, 0x1160],     // Hangul fillers
        [0x17B4, 0x17B5],     // Khmer inherent vowels
        [0x180B, 0x180F],     // Mongolian variation selectors and vowel separator
        [0x200B, 0x200F],     // zero-width space, joiners, directional marks
        [0x202A, 0x202E],     // bidirectional embeddings and overrides
        [0x2060, 0x206F],     // word joiner, invisible operators, deprecated formatting
        [0x3164, 0x3164],     // Hangul filler
        [0xFE00, 0xFE0F],     // variation selectors
        [0xFEFF, 0xFEFF],     // byte order mark
        [0xFFA0, 0xFFA0],     // halfwidth Hangul filler
        [0xFFF9, 0xFFFB],     // interlinear annotation
        [0xE0000, 0xE007F],   // the TAG block
        [0xE0100, 0xE01EF],   // variation selectors supplement
    ];

    /**
     * @return list<string> one "U+XXXX" label per distinct offending code point
     */
    public static function find(string $text): array
    {
        $found = [];
        foreach (self::codePoints($text) as $codePoint) {
            if (self::isInvisible($codePoint) || self::isBadControl($codePoint)) {
                $found[sprintf('U+%04X', $codePoint)] = true;
            }
        }

        return array_keys($found);
    }

    /** Remove every offending code point, and make sure what is left is valid UTF-8. */
    public static function strip(string $text): string
    {
        $clean = '';
        foreach (self::codePoints($text) as $codePoint) {
            if (self::isInvisible($codePoint) || self::isBadControl($codePoint)) {
                continue;
            }
            $clean .= mb_chr($codePoint, 'UTF-8');
        }

        return $clean;
    }

    private static function isInvisible(int $codePoint): bool
    {
        foreach (self::RANGES as [$low, $high]) {
            if ($codePoint >= $low && $codePoint <= $high) {
                return true;
            }
        }

        return false;
    }

    /** Control characters other than tab, newline and carriage return (this also covers ESC, so no terminal tricks). */
    private static function isBadControl(int $codePoint): bool
    {
        if ($codePoint === 0x09 || $codePoint === 0x0A || $codePoint === 0x0D) {
            return false;
        }

        return $codePoint < 0x20 || ($codePoint >= 0x7F && $codePoint <= 0x9F);
    }

    /**
     * @return list<int>
     */
    private static function codePoints(string $text): array
    {
        $clean = mb_convert_encoding($text, 'UTF-8', 'UTF-8'); // replaces invalid bytes with "?"
        $points = mb_str_split($clean, 1, 'UTF-8');

        return array_map(static fn (string $char): int => mb_ord($char, 'UTF-8') ?: 0xFFFD, $points);
    }
}
