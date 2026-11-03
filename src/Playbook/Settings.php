<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Playbook;

use Closure;
use Waynestate\Advisor\Exception\ToolInputError;

/**
 * The one resolver every ADVISOR tool asks for a named setting (ADR-0018):
 * the real environment wins, then the first available Playbook source, else
 * nothing. ADVISOR advises on where a value lives; it never advises secrets.
 */
final class Settings
{
    public const SECRET_LIKE = '/(SECRET|PASSWORD|TOKEN|KEY)$/i';

    /** What resolve() says when a name looks like a secret. */
    public const SECRET_REFUSAL = 'Refusing to look up a key that looks like a secret (it ends in SECRET, PASSWORD, TOKEN or KEY). ADVISOR never returns secret-looking values.';

    public const HOW_TO_GET = <<<'HOW_TO_GET'
composer require waynestate/playbook (VCS repository https://git.wayne.edu/enterprise-apps/playbook.git). PLAYBOOK is private on git.wayne.edu. If you get a 404, ask the Enterprise Applications team for read access.

Linux or macOS: curl --fail -H "PRIVATE-TOKEN: $GITLAB_TOKEN" "https://git.wayne.edu/api/v4/projects/enterprise-apps%2Fplaybook/repository/files/playbook.env/raw?ref=main" -o .env.playbook
Windows (PowerShell): Invoke-WebRequest -Headers @{ 'PRIVATE-TOKEN' = $env:GITLAB_TOKEN } -OutFile .env.playbook "https://git.wayne.edu/api/v4/projects/enterprise-apps%2Fplaybook/repository/files/playbook.env/raw?ref=main"

The token is your own and needs read_api. Never commit .env.playbook.
HOW_TO_GET;

    /**
     * @param list<PlaybookSource> $sources tried in the given order
     * @param (Closure(string): string|null)|null $env reads the environment; defaults to getenv
     */
    public function __construct(
        private readonly array $sources = [],
        private readonly ?Closure $env = null,
    ) {}

    /** The sources a project gets by default: the package, then its .env.playbook. */
    public static function forProject(string $projectRoot): self
    {
        return new self([new PackagePlaybook(), new FilePlaybook($projectRoot)]);
    }

    public static function isSecretLike(string $name): bool
    {
        return preg_match(self::SECRET_LIKE, $name) === 1;
    }

    public function resolve(string $name): Setting
    {
        if (self::isSecretLike($name)) {
            throw new ToolInputError(self::SECRET_REFUSAL);
        }
        $env = $this->envValue($name);
        if ($env !== null) {
            return new Setting($name, $env, 'environment');
        }
        foreach ($this->sources as $source) {
            if (!$source->available()) {
                continue;
            }
            $value = $source->get($name);
            if (is_string($value) && $value !== '') {
                return new Setting($name, $value, $source->name());
            }
        }

        return new Setting($name, null, 'unset');
    }

    public function available(): bool
    {
        foreach ($this->sources as $source) {
            if ($source->available()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every key any available source holds, de-duplicated and sorted, with
     * secret-looking names left out.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->sources as $source) {
            if (!$source->available()) {
                continue;
            }
            foreach (array_keys($source->all()) as $name) {
                $name = (string) $name;
                if (!self::isSecretLike($name)) {
                    $names[$name] = true;
                }
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }

    public function howToGet(): string
    {
        return self::HOW_TO_GET;
    }

    /**
     * Resolves the settings a project declares, so an answer can show each
     * value with its source, or say where the values come from instead.
     *
     * @param list<array{name: string, description: string}> $declared
     * @return array{available: bool, entries: list<array{name: string, description: string, value: string|null, source: string}>, how_to_get: string|null}
     */
    public function describe(array $declared): array
    {
        $entries = [];
        $incomplete = false;
        foreach ($declared as $decl) {
            $name = (string) $decl['name'];
            if (self::isSecretLike($name)) {
                continue;
            }
            $setting = $this->resolve($name);
            if ($setting->source === 'unset') {
                $incomplete = true;
            }
            $entries[] = [
                'name' => $name,
                'description' => (string) $decl['description'],
                'value' => $setting->value,
                'source' => $setting->source,
            ];
        }
        $howToGet = (!$this->available() || $incomplete) ? $this->howToGet() : null;

        return [
            'available' => $this->available(),
            'entries' => $entries,
            'how_to_get' => $howToGet,
        ];
    }

    private function envValue(string $name): ?string
    {
        $read = $this->env ?? self::defaultEnv();
        $value = $read($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function defaultEnv(): Closure
    {
        return static function (string $name): ?string {
            $value = getenv($name);

            return is_string($value) ? $value : null;
        };
    }
}
