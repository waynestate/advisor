<?php

declare(strict_types=1);

namespace Waynestate\Advisor;

use InvalidArgumentException;

/**
 * Every setting ADVISOR reads, from environment variables only. Nothing here
 * is a secret, and the audit log records hashes of arguments, never values.
 */
final class Config
{
    public const DEFAULT_PROCTOR_TIMEOUT = 120;

    public const DEFAULT_MAX_CHARS = 20000;

    /**
     * @param list<string> $groups empty means every group
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $environment,
        public readonly string $projectRoot,
        public readonly bool $readOnly,
        public readonly array $groups,
        public readonly int $proctorTimeout,
        public readonly int $maxChars,
        public readonly ?string $auditLog,
        public readonly string $logLevel,
        public readonly ?string $stackDir,
        public readonly ?string $modulesFile = null,
    ) {
        if ($proctorTimeout < 1 || $proctorTimeout > 900) {
            throw new InvalidArgumentException('ADVISOR_PROCTOR_TIMEOUT must be between 1 and 900 seconds.');
        }
        if ($maxChars < 1000 || $maxChars > 45000) {
            throw new InvalidArgumentException('ADVISOR_MAX_CHARS must be between 1000 and 45000.');
        }
    }

    /**
     * @param array<string, string|false|null>|null $env defaults to the process environment
     */
    public static function fromEnv(?array $env = null, ?string $cwd = null): self
    {
        $env ??= self::processEnv();
        $get = static function (string $key) use ($env): ?string {
            $value = $env[$key] ?? null;

            return is_string($value) && $value !== '' ? $value : null;
        };

        $root = $get('ADVISOR_PROJECT_ROOT') ?? $cwd ?? (getcwd() ?: '.');
        $groups = array_values(array_filter(array_map(
            'trim',
            explode(',', $get('ADVISOR_GROUPS') ?? ''),
        ), static fn(string $g): bool => $g !== ''));

        return new self(
            enabled: self::truthy($get('ADVISOR_ENABLED')),
            environment: strtolower($get('ADVISOR_ENV') ?? $get('APP_ENV') ?? 'local'),
            projectRoot: $root,
            // Read-only wins over everything: it is on unless someone writes the word false.
            readOnly: !self::falsy($get('ADVISOR_READ_ONLY')),
            groups: $groups,
            proctorTimeout: self::int($get('ADVISOR_PROCTOR_TIMEOUT'), self::DEFAULT_PROCTOR_TIMEOUT, 'ADVISOR_PROCTOR_TIMEOUT'),
            maxChars: self::int($get('ADVISOR_MAX_CHARS'), self::DEFAULT_MAX_CHARS, 'ADVISOR_MAX_CHARS'),
            auditLog: $get('ADVISOR_AUDIT_LOG'),
            logLevel: strtolower($get('ADVISOR_LOG_LEVEL') ?? 'warning'),
            stackDir: $get('ADVISOR_STACK_DIR'),
            modulesFile: $get('ADVISOR_MODULES_FILE'),
        );
    }

    /** Production names ADVISOR refuses to run in, whatever else is set (best practice 4.7). */
    public function isProduction(): bool
    {
        return in_array($this->environment, ['production', 'prod'], true);
    }

    /**
     * Why the server must not start, or null when it may.
     */
    public function refusal(): ?string
    {
        if ($this->isProduction()) {
            return 'ADVISOR is a development tool and refuses to run when the environment is production. '
                . 'Install it with `composer require --dev` and deploy with `composer install --no-dev`.';
        }
        if (!$this->enabled) {
            return 'ADVISOR is off. Set ADVISOR_ENABLED=1 in the MCP client configuration (`advisor install` writes it).';
        }

        return null;
    }

    /**
     * @return array<string, string|false|null>
     */
    private static function processEnv(): array
    {
        $env = [];
        foreach (getenv() as $key => $value) {
            $env[(string) $key] = $value;
        }

        return $env;
    }

    private static function truthy(?string $value): bool
    {
        return $value !== null && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function falsy(?string $value): bool
    {
        return $value !== null && in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
    }

    private static function int(?string $value, int $default, string $name): int
    {
        if ($value === null) {
            return $default;
        }
        if (!preg_match('/^\d{1,6}$/', $value)) {
            throw new InvalidArgumentException("{$name} must be a whole number.");
        }

        return (int) $value;
    }
}
