<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use InvalidArgumentException;
use Waynestate\Advisor\Support\Json;

/**
 * The MCP client configuration that starts ADVISOR.
 *
 * The command is a fixed string, `php vendor/bin/advisor mcp`, and the same
 * in PowerShell, zsh and bash (best practice 1.6). Nothing a user types
 * changes it. The generated file is a launcher for a program, so it is
 * reviewed like code and not committed by default (best practice 4.11).
 */
final class ClientConfig
{
    public const SERVER_NAME = 'advisor';

    public const COMMAND = 'php';

    public const ARGS = ['vendor/bin/advisor', 'mcp'];

    public const ENV = ['ADVISOR_ENABLED' => '1'];

    /** client => [file relative to the project, top-level key, needs type: stdio] */
    public const CLIENTS = [
        'claude' => ['.mcp.json', 'mcpServers', false],
        'warriorgpt' => ['.mcp.json', 'mcpServers', false],
        'cursor' => ['.cursor/mcp.json', 'mcpServers', false],
        'vscode' => ['.vscode/mcp.json', 'servers', true],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function entry(bool $withType): array
    {
        $entry = $withType ? ['type' => 'stdio'] : [];

        return $entry + ['command' => self::COMMAND, 'args' => self::ARGS, 'env' => self::ENV];
    }

    public static function file(string $client): string
    {
        return (self::CLIENTS[$client] ?? throw new InvalidArgumentException('Unknown client: ' . $client))[0];
    }

    /**
     * The full JSON file for a client that has none yet.
     */
    public static function render(string $client): string
    {
        [, $key, $type] = self::CLIENTS[$client] ?? throw new InvalidArgumentException('Unknown client: ' . $client);

        return Json::encode([$key => [self::SERVER_NAME => self::entry($type)]]);
    }

    /**
     * Add ADVISOR to an existing file, keeping every other server. Refuses to
     * replace a different `advisor` entry unless $force, because that entry
     * starts a program on this machine.
     */
    public static function merge(string $existingJson, string $client, bool $force = false): string
    {
        [, $key, $type] = self::CLIENTS[$client] ?? throw new InvalidArgumentException('Unknown client: ' . $client);
        $data = trim($existingJson) === '' ? [] : Json::decode($existingJson, 'the existing MCP config');

        $servers = $data[$key] ?? [];
        if (!is_array($servers)) {
            throw new InvalidArgumentException("The existing config has a {$key} entry that is not an object.");
        }
        $wanted = self::entry($type);
        if (isset($servers[self::SERVER_NAME]) && $servers[self::SERVER_NAME] !== $wanted && !$force) {
            throw new InvalidArgumentException('The config already has an "advisor" server that differs from the standard one. Review it, then run again with --force to replace it.');
        }
        $servers[self::SERVER_NAME] = $wanted;
        $data[$key] = $servers;

        return Json::encode($data);
    }

    /** Codex reads TOML; printed only, never written, because merging TOML safely needs a parser. */
    public static function codexToml(): string
    {
        return "[mcp_servers.advisor]\n"
            . 'command = "' . self::COMMAND . "\"\n"
            . 'args = ["' . implode('", "', self::ARGS) . "\"]\n"
            . 'env = { ADVISOR_ENABLED = "1" }' . "\n";
    }
}
