<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reads the bundled copy of CREED's policy.json and answers what state a
 * version is in.
 *
 * CREED's own reader (waynestate/creed) is the only code in the stack that
 * decides a version's state. It is not on a registry yet, so this class mirrors
 * its date arithmetic exactly, and PolicyReaderTest pins the boundary days so a
 * drift shows up as a red test. Swap it for the CREED package once Pantry
 * carries it (see OPEN_QUESTIONS.md).
 */
final class PolicyReader
{
    /**
     * @param array<string, array{versions: array<string, array<string, string>>}> $components
     * @param array<string, array<string, string>>                                  $tools
     * @param array<string, mixed>                                                  $rules
     */
    private function __construct(
        private readonly array $components,
        private readonly array $tools,
        private readonly array $rules,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('The bundled policy has an unsupported schema_version. ADVISOR 0.1 reads schema_version 1.');
        }

        /** @var array<string, array{versions: array<string, array<string, string>>}> $components */
        $components = is_array($data['components'] ?? null) ? $data['components'] : [];
        /** @var array<string, array<string, string>> $tools */
        $tools = is_array($data['tools'] ?? null) ? $data['tools'] : [];
        /** @var array<string, mixed> $rules */
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];

        return new self($components, $tools, $rules);
    }

    /**
     * @return list<string>
     */
    public function components(): array
    {
        return array_map('strval', array_keys($this->components));
    }

    /**
     * @return list<string>
     */
    public function tools(): array
    {
        return array_map('strval', array_keys($this->tools));
    }

    public function toolMinimum(string $tool): ?string
    {
        $minimum = $this->tools[$tool]['minimum'] ?? null;

        return is_string($minimum) ? $minimum : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    public function hasComponent(string $component): bool
    {
        return isset($this->components[$component]);
    }

    /**
     * Map "8.3.12" to the policy's key ("8.3"), or "13.1" to "13".
     */
    public function resolveVersion(string $component, string $version): ?string
    {
        $versions = $this->components[$component]['versions'] ?? [];
        $keys = array_map('strval', array_keys($versions));
        $parts = explode('.', $version);
        foreach ([$version, implode('.', array_slice($parts, 0, 2)), $parts[0]] as $candidate) {
            if (in_array($candidate, $keys, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, string>> version => dates, in the file's order
     */
    public function versions(string $component): array
    {
        $out = [];
        foreach ($this->components[$component]['versions'] ?? [] as $version => $dates) {
            $out[(string) $version] = $dates;
        }

        return $out;
    }

    /**
     * Dates only; the boundary is inclusive. On security_until the version is
     * still Security. After it, Eol for eol_grace_days, then EolGraceExpired.
     * Returns null when the entry has no security_until: this reader does not guess.
     */
    public function stateOf(string $component, string $version, DateTimeImmutable $on): ?State
    {
        $entry = $this->components[$component]['versions'][$version] ?? null;
        if ($entry === null || !isset($entry['security_until'])) {
            return null;
        }

        $day = $on->format('Y-m-d');
        $activeUntil = $entry['active_until'] ?? null;
        $securityUntil = $entry['security_until'];

        if ($activeUntil !== null && $day <= $activeUntil) {
            return State::Active;
        }
        if ($day <= $securityUntil) {
            return State::Security;
        }

        $graceDays = $this->rules['eol_grace_days'] ?? null;
        if (!is_int($graceDays)) {
            return null;
        }
        $graceEnd = (new DateTimeImmutable($securityUntil))->modify('+' . $graceDays . ' days')->format('Y-m-d');

        return $day <= $graceEnd ? State::Eol : State::EolGraceExpired;
    }

    /**
     * The lowest version that is still Active or Security.
     */
    public function oldestSupported(string $component, DateTimeImmutable $on): ?string
    {
        $supported = [];
        foreach (array_keys($this->versions($component)) as $version) {
            $version = (string) $version;
            $state = $this->stateOf($component, $version, $on);
            if ($state === State::Active || $state === State::Security) {
                $supported[] = $version;
            }
        }
        usort($supported, static fn(string $a, string $b): int => version_compare($a, $b));

        return $supported[0] ?? null;
    }
}
