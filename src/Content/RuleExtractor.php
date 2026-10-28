<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

/**
 * Turns CREED handbook chapters into a flat list of citable rules.
 *
 * A rule is a top-level bullet of a chapter. Which of the five app
 * permutations it applies to is worked out from the words in the bullet. That
 * is a heuristic, so every rule carries its file and line: the handbook is the
 * authority, and the list only helps a developer find the right line.
 */
final class RuleExtractor
{
    public const PERMUTATIONS = [
        'raw-php',
        'raw-php-vue',
        'laravel-blade',
        'laravel-blade-vue',
        'laravel-inertia-vue',
    ];

    /**
     * @return list<array{id: string, chapter: string, heading: string, text: string, applies_to: list<string>, source: string}>
     */
    public static function fromChapter(string $chapter, string $markdown): array
    {
        $rules = [];
        $heading = '';
        $inFence = false;
        $current = null;
        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));

        $flush = static function () use (&$current, &$rules, $chapter): void {
            if ($current === null) {
                return;
            }
            $text = trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', $current['text'])));
            if ($text !== '') {
                $rules[] = [
                    'id' => sprintf('%s-%d', $chapter, count($rules) + 1),
                    'chapter' => $chapter,
                    'heading' => $current['heading'],
                    'text' => $text,
                    'applies_to' => self::appliesTo($text),
                    'source' => sprintf('handbook/%s.md:%d', $chapter, $current['line']),
                ];
            }
            $current = null;
        };

        foreach ($lines as $index => $line) {
            if (preg_match('/^```/', $line)) {
                $inFence = !$inFence;
                $flush();
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.*)$/', $line, $m)) {
                $flush();
                $heading = trim($m[1]);
                continue;
            }
            if (preg_match('/^- (.*)$/', $line, $m)) {
                $flush();
                $current = ['text' => $m[1], 'line' => $index + 1, 'heading' => $heading];
                continue;
            }
            if ($current !== null && preg_match('/^\s{2,}(\S.*)$/', $line, $m)) {
                $current['text'] .= ' ' . preg_replace('/^-\s+/', '', $m[1]);
                continue;
            }
            $flush();
        }
        $flush();

        return $rules;
    }

    /**
     * @return list<string>
     */
    public static function appliesTo(string $text): array
    {
        $set = self::PERMUTATIONS;
        if (preg_match('/\braw[- ]php\b/i', $text)) {
            // A rule that names raw PHP next to Laravel is a contrast, not a restriction.
            return $set;
        }
        $keep = static fn (array $allowed): array => array_values(array_intersect($set, $allowed));

        if (preg_match('/\b(inertia)\b/i', $text)) {
            $set = $keep(['laravel-inertia-vue']);
        }
        if (preg_match('/\b(blade)\b/i', $text)) {
            $set = $keep(['laravel-blade', 'laravel-blade-vue']);
        }
        if (preg_match('/\b(laravel|artisan|eloquent|illuminate|larastan)\b/i', $text)) {
            $set = $keep(['laravel-blade', 'laravel-blade-vue', 'laravel-inertia-vue']);
        }
        if (preg_match('/\b(vue)\b/i', $text)) {
            $set = $keep(['raw-php-vue', 'laravel-blade-vue', 'laravel-inertia-vue']);
        }

        return $set === [] ? self::PERMUTATIONS : $set;
    }
}
