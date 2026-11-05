#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * proof: the dress rehearsal for the tour.
 *
 * It walks a corpus of units through the real `bin/advisor mcp` server and
 * keeps one honest JSONL line per unit. The flags are documented in the
 * README; this file only parses them and hands off to ProofRunner.
 */

use Waynestate\Advisor\Proof\ProofRunner;

require dirname(__DIR__) . '/vendor/autoload.php';

$known = [
    'units',
    'corpus',
    'work',
    'out',
    'proctor-bin',
    'booster-bin',
    'advisor-bin',
    'tool-commit',
    'date',
    'only',
    'limit',
    'timeout',
];

$options = [];
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        fwrite(STDERR, "Unknown argument {$arg}.\n");
        exit(2);
    }
    [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
    if (!in_array($name, $known, true)) {
        fwrite(STDERR, "Unknown flag --{$name}; known flags: " . implode(', ', $known) . ".\n");
        exit(2);
    }
    if ($value === null || trim($value) === '') {
        fwrite(STDERR, "Flag --{$name} needs a value after the =.\n");
        exit(2);
    }
    $options[$name] = $value;
}

try {
    $runner = ProofRunner::fromOptions($options);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'proof: ' . $e->getMessage() . "\n");
    exit(2);
}

$result = $runner->run();

$all = $result['summary']['all'];
$apps = $result['summary']['apps'];
printf(
    "units: %d pass %d flagged %d tool-error %d not-applicable %d\n",
    array_sum($all),
    $all['pass'],
    $all['flagged'],
    $all['tool-error'],
    $all['not-applicable'],
);
printf(
    "apps:  %d pass %d flagged %d tool-error %d not-applicable %d\n",
    array_sum($apps),
    $apps['pass'],
    $apps['flagged'],
    $apps['tool-error'],
    $apps['not-applicable'],
);

exit($result['exit']);
