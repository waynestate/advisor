<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Support;

use JsonException;
use RuntimeException;

final class Json
{
    private function __construct() {}

    /**
     * @return array<mixed>
     */
    public static function decodeFile(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        return self::decode($raw, $path);
    }

    /**
     * @return array<mixed>
     */
    public static function decode(string $raw, string $label = 'JSON'): array
    {
        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid JSON in {$label}: {$e->getMessage()}", 0, $e);
        }
        if (!is_array($data)) {
            throw new RuntimeException("Expected a JSON object or list in {$label}.");
        }

        return $data;
    }

    /** Stable, readable output so generated files diff cleanly and hash the same everywhere. */
    public static function encode(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
