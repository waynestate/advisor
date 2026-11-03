<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Playbook;

/**
 * The .env.playbook file a developer downloads into the project root per
 * ADR-0018. Plain KEY=value lines, read once per instance, never executed
 * or interpolated: this file is data, not a shell script.
 */
final class FilePlaybook implements PlaybookSource
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    public function __construct(
        private readonly string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'env.playbook';
    }

    public function available(): bool
    {
        return is_file($this->path()) && is_readable($this->path());
    }

    public function get(string $key): ?string
    {
        $value = $this->load()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->load();
    }

    private function path(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . '.env.playbook';
    }

    /** @return array<string, string> */
    private function load(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }
        $values = $this->available() ? $this->parse() : [];
        $this->values = $values;

        return $values;
    }

    /** @return array<string, string> */
    private function parse(): array
    {
        $values = [];
        foreach (file($this->path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }
            $key = trim(substr($line, 0, $position));
            if ($key === '') {
                continue;
            }
            $values[$key] = self::unquote(trim(substr($line, $position + 1)));
        }

        return $values;
    }

    /** Strip one pair of matching single or double quotes around a value. */
    private static function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, $length - 2);
            }
        }

        return $value;
    }
}
