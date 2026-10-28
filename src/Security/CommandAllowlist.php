<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

use Waynestate\Advisor\Exception\ToolInputError;

/**
 * Allowlists the program and the shape of its arguments, not just its name
 * (best practice 2.6). A tool argument can fill the value of an option; it can
 * never add an option, a subcommand or a second program.
 *
 * Every program ADVISOR starts is described here, and nothing a model sends
 * chooses which one.
 */
final class CommandAllowlist
{
    /** @var array<string, string> option => regex its value must match */
    private const PROCTOR_OPTIONS = [
        '--path' => '#^[^\0]{1,1024}$#',
        '--format' => '#^json$#',
        '--policy' => '#^[^\0]{1,1024}$#',
        '--timeout' => '#^[1-9][0-9]{0,3}$#',
        '--today' => '#^\d{4}-\d{2}-\d{2}$#',
    ];

    /**
     * BOOSTER's `plan` is the only subcommand ADVISOR may call: it reads the
     * app's manifests and prints a plan, and it changes nothing. `run` is the
     * command that edits files, and it is not on this list.
     *
     * @var array<string, string> option => regex its value must match
     */
    private const BOOSTER_OPTIONS = [
        '--path' => '#^[^\0]{1,1024}$#',
        '--format' => '#^json$#',
        '--policy' => '#^[^\0]{1,1024}$#',
        '--to-php' => '#^\d{1,2}\.\d{1,2}$#',
        '--to-laravel' => '#^\d{1,2}$#',
    ];

    /**
     * Expected shape: [php, <proctor script>, check, --option=value ...]
     *
     * @param list<string> $argv
     *
     * @throws ToolInputError when anything is off
     */
    public static function assertProctor(array $argv, string $php, string $proctorScript): void
    {
        if (count($argv) < 4 || $argv[0] !== $php || $argv[1] !== $proctorScript || $argv[2] !== 'check') {
            throw new ToolInputError('That command is not on the allowlist.');
        }
        $seen = [];
        foreach (array_slice($argv, 3) as $arg) {
            if (!preg_match('/^(--[a-z-]+)=(.*)$/s', $arg, $m)) {
                throw new ToolInputError('That command is not on the allowlist.');
            }
            [, $option, $value] = $m;
            if (!isset(self::PROCTOR_OPTIONS[$option]) || isset($seen[$option]) || !preg_match(self::PROCTOR_OPTIONS[$option], $value)) {
                throw new ToolInputError('That command is not on the allowlist.');
            }
            $seen[$option] = true;
        }
        if (!isset($seen['--path'], $seen['--policy'], $seen['--format'])) {
            throw new ToolInputError('That command is not on the allowlist.');
        }
    }

    /**
     * Expected shape: [php, <booster script>, plan, --option=value ...]
     *
     * @param list<string> $argv
     *
     * @throws ToolInputError when anything is off
     */
    public static function assertBooster(array $argv, string $php, string $boosterScript): void
    {
        if (count($argv) < 4 || $argv[0] !== $php || $argv[1] !== $boosterScript || $argv[2] !== 'plan') {
            throw new ToolInputError('That command is not on the allowlist.');
        }
        $seen = [];
        foreach (array_slice($argv, 3) as $arg) {
            if (!preg_match('/^(--[a-z-]+)=(.*)$/s', $arg, $m)) {
                throw new ToolInputError('That command is not on the allowlist.');
            }
            [, $option, $value] = $m;
            if (!isset(self::BOOSTER_OPTIONS[$option]) || isset($seen[$option]) || !preg_match(self::BOOSTER_OPTIONS[$option], $value)) {
                throw new ToolInputError('That command is not on the allowlist.');
            }
            $seen[$option] = true;
        }
        if (!isset($seen['--path'], $seen['--policy'], $seen['--format'])) {
            throw new ToolInputError('That command is not on the allowlist.');
        }
    }
}
