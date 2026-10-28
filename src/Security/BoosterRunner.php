<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Support\Invisible;
use Waynestate\Advisor\Support\Json;

/**
 * Runs BOOSTER's read-only `plan` command and returns the ordered upgrade hops.
 *
 * It plans; it never runs a hop. The command that edits files is `booster run`,
 * and this runner is only ever allowed to call `plan` (see CommandAllowlist).
 *
 * The binary is found in two places, in order: the app's own vendor/bin/booster
 * (so a model can never point ADVISOR at another program), then the operator's
 * ADVISOR_BOOSTER_BIN. Neither is chosen by the model.
 */
final class BoosterRunner
{
    public function __construct(
        private readonly PathGuard $guard,
        private readonly ProcessRunner $runner,
        private readonly string $policyFile,
        private readonly int $timeoutSeconds,
        private readonly ?string $configuredBin,
        private readonly string $php = PHP_BINARY,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(string $path, ?string $toPhp = null, ?string $toLaravel = null): array
    {
        if ($toPhp !== null && !preg_match('/^\d{1,2}\.\d{1,2}$/', $toPhp)) {
            throw new ToolInputError('to_php must be a PHP version, major.minor, for example 8.4.');
        }
        if ($toLaravel !== null && !preg_match('/^\d{1,2}$/', $toLaravel)) {
            throw new ToolInputError('to_laravel must be a Laravel major version, for example 11.');
        }

        $app = $this->guard->resolve($path);
        if (!is_dir($app)) {
            throw new ToolInputError('The path must be a directory that holds the app.');
        }

        $script = $this->locateBooster();

        $argv = [
            $this->php,
            $script,
            'plan',
            '--path=' . $app,
            '--format=json',
            '--policy=' . $this->policyFile,
        ];
        if ($toPhp !== null) {
            $argv[] = '--to-php=' . $toPhp;
        }
        if ($toLaravel !== null) {
            $argv[] = '--to-laravel=' . $toLaravel;
        }
        CommandAllowlist::assertBooster($argv, $this->php, $script);

        $result = $this->runner->run($argv, $this->guard->root(), $this->timeoutSeconds);
        if ($result->timedOut) {
            throw new ToolInputError('BOOSTER took longer than ADVISOR_BOOSTER_TIMEOUT and was stopped. Raise the timeout if the app is large.');
        }
        // plan exits 0 with the plan on stdout; 2 is bad usage (the message is on stderr).
        // 1 is a run-only code, but any non-zero exit is an error here.
        if ($result->exitCode !== 0) {
            $message = trim(Redactor::redact(Invisible::strip($result->stderr)));
            throw new ToolInputError('BOOSTER could not plan the upgrade: ' . substr($message === '' ? 'exit code ' . $result->exitCode : $message, 0, 500));
        }

        try {
            $plan = Json::decode($result->stdout, 'the BOOSTER plan');
        } catch (\RuntimeException) {
            throw new ToolInputError('BOOSTER did not return a JSON plan.');
        }

        return $this->shape($plan, $app, $toPhp, $toLaravel);
    }

    /**
     * The app's own vendor/bin/booster wins; then the operator's configured path.
     */
    private function locateBooster(): string
    {
        try {
            $script = $this->guard->resolve('vendor/bin/booster');
            if (is_file($script)) {
                return $script;
            }
        } catch (ToolInputError) {
            // No vendor/bin/booster in the project; fall through to the configured path.
        }

        if ($this->configuredBin !== null) {
            $real = realpath($this->configuredBin);
            if ($real !== false && is_file($real) && $this->looksLikePhp($real)) {
                return $real;
            }
            throw new ToolInputError('ADVISOR_BOOSTER_BIN is set but does not point at a readable PHP script. Point it at booster\'s bin/booster (or a booster.phar).');
        }

        throw new ToolInputError(
            'BOOSTER is not configured. Install it in the app with `composer require --dev waynestate/booster`, or set ADVISOR_BOOSTER_BIN to the booster binary (for example /path/to/booster/bin/booster).',
        );
    }

    /** A PHP script, a composer bin proxy, or a phar all start with one of these. */
    private function looksLikePhp(string $file): bool
    {
        $head = (string) @file_get_contents($file, false, null, 0, 32);
        $head = trim($head, "\xEF\xBB\xBF \t\r\n");

        return str_starts_with($head, '<?php') || str_starts_with($head, '#!');
    }

    /**
     * @param array<mixed> $plan
     *
     * @return array<string, mixed>
     */
    private function shape(array $plan, string $app, ?string $toPhp, ?string $toLaravel): array
    {
        $plan = $this->cleanArray($plan);

        $project = is_array($plan['project'] ?? null) ? $plan['project'] : [];
        // The plan echoes back the absolute path we passed; show the project-relative one.
        $project['path'] = $this->guard->relative($app);

        $steps = [];
        foreach (is_array($plan['steps'] ?? null) ? $plan['steps'] : [] as $step) {
            if (is_array($step)) {
                $steps[] = $step;
            }
        }

        $warnings = [];
        foreach (is_array($plan['warnings'] ?? null) ? $plan['warnings'] : [] as $warning) {
            if (is_string($warning)) {
                $warnings[] = $warning;
            }
        }

        $command = 'booster run --path=' . $this->guard->relative($app);
        if ($toPhp !== null) {
            $command .= ' --to-php=' . $toPhp;
        }
        if ($toLaravel !== null) {
            $command .= ' --to-laravel=' . $toLaravel;
        }

        return [
            'tool' => 'booster',
            'app' => $this->guard->relative($app),
            'project' => $project,
            'today' => is_string($plan['today'] ?? null) ? $plan['today'] : '',
            // The plan names the policy file by its host path; that is ADVISOR's own
            // layout, so the answer says what it is instead of where it lives.
            'policy' => 'ADVISOR bundled CREED policy.json',
            'target_php' => is_string($plan['target_php'] ?? null) ? $plan['target_php'] : null,
            'target_laravel' => is_string($plan['target_laravel'] ?? null) ? $plan['target_laravel'] : null,
            'steps' => $steps,
            'warnings' => $warnings,
            'next_command' => $command,
            'notice' => 'This is a plan from BOOSTER. It changes nothing; `booster run` does the work, one hop at a time, and the gates decide.',
        ];
    }

    /**
     * Strip invisible characters and redact secrets from every string in the
     * plan, which came from a child process and the app's own manifests.
     *
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private function cleanArray(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = $this->clean($item);
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            return Redactor::redact(Invisible::strip($value));
        }
        if (is_array($value)) {
            return $this->cleanArray($value);
        }

        return $value;
    }
}
