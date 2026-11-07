<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

/**
 * Drops the image boilerplate that means nothing in a text answer: centred
 * hero, badge and cast blocks, stray img tags on their own line, Markdown
 * image lines (linked or not) whose target is relative or a GIF, and rows made
 * of nothing but images (badge rows). Prose, tables and code survive: JSON
 * passes through untouched, and nothing inside a fenced code block is
 * rewritten.
 *
 * The generator runs it on the way in, and ContentBundle runs it again on the
 * way out, so a bundle generated before a rule existed is still served clean.
 * Running it twice changes nothing. A cast photo is worth a thousand words; an
 * agent paying by the token would rather have the words.
 */
final class ImageTrim
{
    /** One Markdown image, optionally wrapped in a link; group 1 is the image target. */
    private const IMAGE = '(?:\[\s*)?!\[[^\]]*\]\(\s*<?([^()<>\s]+)>?(?:\s+[^()]*)?\)(?:\s*\]\([^()\s]*\))?';

    public static function trim(string $text): string
    {
        if (str_starts_with($text, '{') || (str_starts_with($text, '[') && !str_starts_with($text, '[!['))) {
            return $text;
        }

        // Even segments are outside code fences, odd ones are the fence lines
        // themselves, and every segment after an opening fence is code. The
        // group keeps the fence line in the split so it survives the rebuild.
        $parts = preg_split('/^(```[^\n]*|~~~[^\n]*)$/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $part) {
            if ($i % 4 === 0) {
                $parts[$i] = self::trimLines((string) $part);
            }
        }

        return implode('', $parts);
    }

    private static function trimLines(string $text): string
    {
        // Centred div/p blocks exist only to hold images; keep any that do not.
        $text = (string) preg_replace_callback(
            '#<(div|p)\s[^>]*align=["\']center["\'][^>]*>.*?</\1>#is',
            static fn(array $m): string => str_contains($m[0], '<img') ? '' : $m[0],
            $text,
        );
        // An img tag, optionally wrapped in a link, that owns its line.
        $text = (string) preg_replace('/^[ \t]*(?:<a\s[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>)?[ \t]*$/m', '', $text);
        // A row of two or more images and nothing else is a badge row.
        $text = (string) preg_replace('/^[ \t]*' . self::IMAGE . '(?:[ \t]+' . self::IMAGE . ')+[ \t]*$/m', '', $text);
        // One image on its own line (linked or not) whose target is relative or a GIF.
        $text = (string) preg_replace_callback(
            '/^[ \t]*' . self::IMAGE . '[ \t]*$/m',
            static function (array $m): string {
                if (str_starts_with($m[1], 'http') && !str_ends_with(strtolower($m[1]), '.gif')) {
                    return $m[0];
                }

                return '';
            },
            $text,
        );
        // Three or more blank lines become one.
        $text = (string) preg_replace("/\n{4,}/", "\n\n", $text);

        return $text;
    }
}
