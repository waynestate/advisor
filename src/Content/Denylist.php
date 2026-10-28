<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

use RuntimeException;

/**
 * The private denylist: internal hostnames, people, and other strings that
 * must not appear in the bundled content. Loaded from a file named by the
 * `ADVISOR_PRIVATE_DENYLIST` environment variable (one literal or `regex:`
 * pattern per line, `#` comments). If the variable is unset or the file is
 * missing, the denylist is empty and the private part is skipped.
 *
 * The file is kept outside the repository so that publishing ADVISOR does not
 * publish the very strings it guards against.
 */
final class Denylist
{
    /** @var list<string> compiled PCRE patterns */
    private array $patterns;

    /** @param list<string> $patterns */
    private function __construct(array $patterns)
    {
        $this->patterns = $patterns;
    }

    /**
     * Loads the denylist from the file named by the `ADVISOR_PRIVATE_DENYLIST`
     * environment variable. Returns an empty denylist if the variable is unset
     * or the file is missing.
     */
    public static function fromEnv(): self
    {
        $path = getenv('ADVISOR_PRIVATE_DENYLIST');
        if ($path === false || $path === '' || !is_file($path)) {
            return new self([]);
        }

        return self::fromFile($path);
    }

    /**
     * Loads the denylist from a specific file. Returns an empty denylist if
     * the file is missing.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return new self([]);
        }

        $patterns = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'regex:')) {
                $pattern = substr($line, 6);
                if (@preg_match($pattern, '') === false) {
                    throw new RuntimeException("Invalid regex in the private denylist: {$pattern}");
                }
                $patterns[] = $pattern;
            } else {
                $patterns[] = '/' . preg_quote($line, '/') . '/i';
            }
        }

        return new self($patterns);
    }

    /**
     * Returns true if the denylist is empty (the private part is skipped).
     */
    public function isEmpty(): bool
    {
        return $this->patterns === [];
    }

    /**
     * Replaces every match of the denylist patterns with the given replacement.
     */
    public function replace(string $text, string $replacement = ''): string
    {
        foreach ($this->patterns as $pattern) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Returns the first match of each pattern that matches the text, in the
     * order the patterns were loaded.
     *
     * @return list<string>
     */
    public function find(string $text): array
    {
        $found = [];
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $found[] = $m[0];
            }
        }

        return $found;
    }
}
