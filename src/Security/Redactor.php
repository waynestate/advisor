<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

/**
 * Blanks values that look like secrets in text that came from a child process
 * or a project file (best practice 4.8). A blunt tool on purpose: a false
 * positive costs a word, a miss costs a key.
 */
final class Redactor
{
    public const MARK = '[redacted]';

    private const PATTERNS = [
        // KEY=value or "key": "value" for names that smell like credentials
        '/((?:api[_-]?key|secret|token|passwd|password|private[_-]?key|authorization|client[_-]?secret)["\']?\s*[:=]\s*["\']?)(?:(?:Bearer|Basic)\s+)?[^\s"\',;]{4,}/i',
        // Bearer tokens
        '/(Bearer\s+)[A-Za-z0-9._~+\/=-]{8,}/',
        // PEM blocks
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s',
        // Well-known token shapes
        '/\b(?:ghp|gho|ghu|ghs|ghr|github_pat|glpat|xox[abprs])[-_][A-Za-z0-9_-]{16,}/',
        '/\bAKIA[0-9A-Z]{16}\b/',
    ];

    public static function redact(string $text): string
    {
        $out = $text;
        foreach (self::PATTERNS as $pattern) {
            $replaced = preg_replace_callback(
                $pattern,
                static fn (array $m): string => ($m[1] ?? '') . self::MARK,
                $out,
            );
            $out = $replaced ?? $out;
        }

        return $out;
    }
}
