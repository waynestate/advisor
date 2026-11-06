<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Proof;

use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Symfony\Component\Process\Process;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Security\ProctorRunner;

/**
 * The proof run: it starts the real `bin/advisor mcp` server, asks each unit
 * of a corpus for its PROCTOR report, its upgrade plan and its PHP support
 * state, and keeps one honest JSONL line per unit. It is the dress rehearsal
 * for the tour: every act is performed, nothing is booked.
 *
 * It never edits a unit. The unit is copied into a scratch folder (secrets,
 * git history and symlinks left behind), the server is pointed at the copy,
 * and the copy is removed when the unit is judged.
 */
final class ProofRunner
{
    /** The exact line ADVISOR returns when a tool crashes (see ToolInvoker). */
    private const UNEXPECTED_ERROR_TEXT = 'The tool failed unexpectedly. The details are in the local ADVISOR log.';

    private const OUTCOME_PASS = 'pass';

    private const OUTCOME_FLAGGED = 'flagged';

    private const OUTCOME_TOOL_ERROR = 'tool-error';

    private const OUTCOME_NOT_APPLICABLE = 'not-applicable';

    private const KINDS = ['app', 'library', 'template', 'test-site'];

    private const REQUIRED_TOOLS = ['run_proctor_check', 'upgrade_path', 'policy_lookup'];

    private const PROTOCOL_VERSION = '2025-06-18';

    private const REFUSAL_TEXT_CHARS = 120;

    private const ERROR_TEXT_CHARS = 240;

    private const MAX_STDERR_TAIL = 2000;

    private string $stdoutBuffer = '';

    private string $stderrBuffer = '';

    /** @var array<int|string, list<array<string, mixed>>> */
    private array $responses = [];

    /** @var list<string> */
    private array $strayLines = [];

    private ?int $lastExit = null;

    /** @var list<array{id: string, dir: string, kind: string}> */
    private readonly array $units;

    /**
     * @param list<array{id: string, dir: string, kind: string}> $units
     */
    private function __construct(
        private readonly string $corpusDir,
        private readonly string $workDir,
        private readonly string $outFile,
        private readonly string $proctorBin,
        private readonly string $boosterBin,
        private readonly string $advisorBin,
        private readonly ?string $toolCommit,
        private readonly string $date,
        private readonly int $timeout,
        array $units,
    ) {
        $this->units = $units;
    }

    /**
     * Validates the CLI options and resolves the units to run.
     *
     * @param array<string, string> $options
     */
    public static function fromOptions(array $options): self
    {
        $unitsFile = self::required($options, 'units');
        $corpus = self::required($options, 'corpus');
        $work = self::required($options, 'work');
        $out = self::required($options, 'out');
        $proctorBin = self::required($options, 'proctor-bin');
        $boosterBin = self::required($options, 'booster-bin');
        $advisorBin = $options['advisor-bin'] ?? dirname(__DIR__, 2) . '/bin/advisor';

        if (!is_file($unitsFile) || !is_readable($unitsFile)) {
            throw new InvalidArgumentException('The units file does not exist or is not readable.');
        }
        if (!is_dir($corpus)) {
            throw new InvalidArgumentException('The corpus directory does not exist.');
        }
        foreach (['proctor-bin' => $proctorBin, 'booster-bin' => $boosterBin, 'advisor-bin' => $advisorBin] as $name => $bin) {
            if (!is_file($bin) || !is_readable($bin)) {
                throw new InvalidArgumentException("The {$name} file does not exist or is not readable.");
            }
        }

        $toolCommit = null;
        if (isset($options['tool-commit']) && trim($options['tool-commit']) !== '') {
            $toolCommit = trim($options['tool-commit']);
            if (preg_match('/^[0-9a-fA-F]{7,40}$/', $toolCommit) !== 1) {
                throw new InvalidArgumentException('--tool-commit must be a git sha of 7 to 40 hex characters.');
            }
        }

        $date = $options['date'] ?? gmdate('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException('--date must look like YYYY-MM-DD.');
        }

        $timeout = 300;
        if (isset($options['timeout'])) {
            if (preg_match('/^\d{1,4}$/', $options['timeout']) !== 1 || (int) $options['timeout'] < 1) {
                throw new InvalidArgumentException('--timeout must be a whole number of seconds.');
            }
            $timeout = (int) $options['timeout'];
            if ($timeout > 3600) {
                throw new InvalidArgumentException('--timeout must be at most 3600 seconds.');
            }
        }

        $limit = null;
        if (isset($options['limit'])) {
            if (preg_match('/^\d{1,5}$/', $options['limit']) !== 1 || (int) $options['limit'] < 1) {
                throw new InvalidArgumentException('--limit must be a whole number of units.');
            }
            $limit = (int) $options['limit'];
        }

        $units = self::parseUnits($unitsFile, $corpus);

        $only = [];
        if (isset($options['only']) && trim($options['only']) !== '') {
            foreach (explode(',', $options['only']) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $found = false;
                foreach ($units as $unit) {
                    if ($unit['id'] === $part) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new InvalidArgumentException("Unknown unit id in --only: {$part}");
                }
                $only[$part] = true;
            }
        }

        $selected = [];
        foreach ($units as $unit) {
            if ($only !== [] && !isset($only[$unit['id']])) {
                continue;
            }
            $selected[] = $unit;
            if ($limit !== null && count($selected) >= $limit) {
                break;
            }
        }
        if ($selected === []) {
            throw new InvalidArgumentException('No units selected; the units file holds no matching lines.');
        }

        if (!is_dir($work) && !mkdir($work, 0775, true) && !is_dir($work)) {
            throw new InvalidArgumentException('The work directory could not be created.');
        }
        if (!is_dir(dirname($out))) {
            throw new InvalidArgumentException('The folder holding the output file does not exist.');
        }

        return new self($corpus, $work, $out, $proctorBin, $boosterBin, $advisorBin, $toolCommit, $date, $timeout, $selected);
    }

    /**
     * Runs every selected unit and writes the JSONL file.
     *
     * @return array{exit: int, lines: list<array<string, mixed>>, summary: array{all: array<string, int>, apps: array<string, int>}}
     */
    public function run(): array
    {
        $lines = [];
        $summary = ['all' => self::emptyCounts(), 'apps' => self::emptyCounts()];
        foreach ($this->units as $unit) {
            $line = $this->runUnit($unit);
            $lines[] = $line;
            $outcome = (string) $line['outcome'];
            $summary['all'][$outcome] = ($summary['all'][$outcome] ?? 0) + 1;
            if ($unit['kind'] === 'app') {
                $summary['apps'][$outcome] = ($summary['apps'][$outcome] ?? 0) + 1;
            }
        }

        $jsonl = '';
        foreach ($lines as $line) {
            $encoded = json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new RuntimeException('Could not encode a log line.');
            }
            $jsonl .= $encoded . "\n";
        }
        if (file_put_contents($this->outFile, $jsonl) === false) {
            throw new RuntimeException('Could not write the JSONL output file.');
        }

        return [
            'exit' => $summary['all'][self::OUTCOME_TOOL_ERROR] > 0 ? 1 : 0,
            'lines' => $lines,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function emptyCounts(): array
    {
        return [
            self::OUTCOME_PASS => 0,
            self::OUTCOME_FLAGGED => 0,
            self::OUTCOME_TOOL_ERROR => 0,
            self::OUTCOME_NOT_APPLICABLE => 0,
        ];
    }

    /**
     * @param array<string, string> $options
     */
    private static function required(array $options, string $name): string
    {
        $value = $options[$name] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("--{$name} is required.");
        }

        return $value;
    }

    /**
     * @return list<array{id: string, dir: string, kind: string}>
     */
    private static function parseUnits(string $unitsFile, string $corpus): array
    {
        $rawLines = file($unitsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rawLines === false) {
            throw new InvalidArgumentException('The units file could not be read.');
        }
        $units = [];
        $seen = [];
        foreach ($rawLines as $index => $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = preg_split('/\s+/', $line) ?: [];
            if (count($parts) !== 3) {
                throw new InvalidArgumentException('Units line ' . ($index + 1) . " must be '<id> <dir> <kind>'.");
            }
            [$id, $dir, $kind] = $parts;
            if ($id === '') {
                throw new InvalidArgumentException('Units line ' . ($index + 1) . ' has no id.');
            }
            if (!in_array($kind, self::KINDS, true)) {
                throw new InvalidArgumentException('Units line ' . ($index + 1) . " has an unknown kind {$kind}; use " . implode(', ', self::KINDS) . '.');
            }
            if (isset($seen[$id])) {
                throw new InvalidArgumentException("Duplicate unit id {$id} on units line " . ($index + 1) . '.');
            }
            $seen[$id] = true;
            $absolute = rtrim($corpus, '/') . '/' . $dir;
            if (!is_dir($absolute)) {
                throw new InvalidArgumentException("Unit {$id} points at a directory that does not exist under the corpus.");
            }
            $units[] = ['id' => $id, 'dir' => $absolute, 'kind' => $kind];
        }

        return $units;
    }

    /**
     * @param array{id: string, dir: string, kind: string} $unit
     * @return array<string, mixed>
     */
    private function runUnit(array $unit): array
    {
        $started = microtime(true);
        $unitCommit = $this->gitHead($unit['dir']);
        $work = $this->workDir . '/' . $unit['id'];
        try {
            $this->copyRecursive($unit['dir'], $work);
            if (!$this->hasPhpFile($work)) {
                return $this->logLine($unit, $unitCommit, $started, self::OUTCOME_NOT_APPLICABLE, 'advisor mcp', null, []);
            }
            $this->ensureProctorShim($work);

            return $this->runServer($unit, $unitCommit, $started, $work);
        } catch (RuntimeException $e) {
            $detail = ['error' => $this->buildError($e->getMessage(), $unit, $work)];

            return $this->logLine($unit, $unitCommit, $started, self::OUTCOME_TOOL_ERROR, 'advisor mcp', $this->lastExit, $detail);
        } finally {
            $this->removeTree($work);
        }
    }

    /**
     * @param array{id: string, dir: string, kind: string} $unit
     * @return array<string, mixed>
     */
    private function runServer(array $unit, ?string $unitCommit, float $started, string $work): array
    {
        // composer-audit phones home, and the proof run stays offline.
        $checks = array_values(array_diff(ProctorRunner::CHECKS, ['composer-audit']));
        $calls = [
            ['name' => 'run_proctor_check', 'arguments' => ['checks' => $checks]],
            ['name' => 'upgrade_path', 'arguments' => []],
        ];
        $phpVersion = $this->composerPhpVersion($work);
        if ($phpVersion !== null) {
            $calls[] = ['name' => 'policy_lookup', 'arguments' => ['component' => 'php', 'version' => $phpVersion]];
        }
        $command = 'advisor mcp; tools/call ' . implode(', ', array_map(static fn(array $call): string => $call['name'], $calls));

        $this->stdoutBuffer = '';
        $this->stderrBuffer = '';
        $this->responses = [];
        $this->strayLines = [];
        $this->lastExit = null;

        $process = null;
        $pipes = null;
        $exitCode = null;
        $callResults = [];
        try {
            [$process, $pipes] = $this->startServer($work);
            $deadline = microtime(true) + $this->timeout;
            $this->send($pipes[0], [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => self::PROTOCOL_VERSION,
                    'capabilities' => new stdClass(),
                    'clientInfo' => ['name' => 'proof', 'version' => '1.0'],
                ],
            ]);
            $this->expectResponse($process, $pipes[1], $pipes[2], 1, $deadline, 'the initialize answer');
            $this->send($pipes[0], ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
            $this->send($pipes[0], ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new stdClass()]);
            $list = $this->expectResponse($process, $pipes[1], $pipes[2], 2, $deadline, 'the tools/list answer');
            $missing = $this->missingTools($list);
            if ($missing !== []) {
                throw new RuntimeException('tools/list lacks ' . implode(', ', $missing) . '.');
            }

            $nextId = 3;
            foreach ($calls as $call) {
                $name = (string) $call['name'];
                $callStart = microtime(true);
                $arguments = $call['arguments'] === [] ? new stdClass() : $call['arguments'];
                $this->send($pipes[0], ['jsonrpc' => '2.0', 'id' => $nextId, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments]]);
                $response = $this->expectResponse($process, $pipes[1], $pipes[2], $nextId, $deadline, "the {$name} answer");
                $callResults[$name] = $this->judgeCall($name, $response, (int) round((microtime(true) - $callStart) * 1000));
                $data = $callResults[$name]['data'] ?? null;
                if (is_array($data)) {
                    if ($name === 'run_proctor_check') {
                        $problem = $this->validateProctorData($data);
                    } elseif ($name === 'upgrade_path') {
                        $problem = $this->validateBoosterData($data);
                    } else {
                        // The only remaining tool is policy_lookup.
                        $problem = $this->validatePolicyData($data);
                    }
                    if ($problem !== null) {
                        throw new RuntimeException($problem);
                    }
                }
                $nextId++;
            }

            fclose($pipes[0]);
            $exitCode = $this->waitForExit($process, $pipes[1], $pipes[2]);
            if ($exitCode !== 0) {
                throw new RuntimeException('The server exited with code ' . $exitCode . ' after the calls.');
            }
            if ($this->strayLines !== []) {
                throw new RuntimeException('stdout held a line that is not JSON-RPC: ' . $this->strayLines[0]);
            }
        } finally {
            $this->closeServer($process, $pipes);
        }

        $detail = [
            'calls' => [],
            'proctor_failed_checks' => [],
            'proctor_findings' => 0,
            'hops' => 0,
            'warnings' => 0,
            'policy_state' => null,
        ];
        $flagged = false;
        foreach ($callResults as $name => $call) {
            $entry = ['ok' => $call['ok'], 'refused' => $call['refused'], 'ms' => $call['ms']];
            if (isset($call['refused_text'])) {
                $entry['refused_text'] = $call['refused_text'];
            }
            $detail['calls'][$name] = $entry;
            if ($call['refused']) {
                // A refusal means the tool could not say what it says about this app, which is news worth a flag.
                $flagged = true;
                continue;
            }
            $data = $call['data'] ?? [];
            switch ($name) {
                case 'run_proctor_check':
                    $detail['proctor_findings'] = count($data['findings']);
                    $failed = [];
                    foreach ($data['findings'] as $finding) {
                        if (is_array($finding) && ($finding['status'] ?? null) === 'fail') {
                            $check = (string) ($finding['check'] ?? '');
                            if ($check !== '') {
                                $failed[] = $check;
                            }
                        }
                    }
                    $detail['proctor_failed_checks'] = $failed;
                    if (($data['result'] ?? null) === 'fail' || $failed !== []) {
                        $flagged = true;
                    }
                    break;
                case 'upgrade_path':
                    $detail['hops'] = count($data['steps']);
                    $detail['warnings'] = count($data['warnings']);
                    if ($data['warnings'] !== []) {
                        $flagged = true;
                    }
                    break;
                case 'policy_lookup':
                    $state = (string) ($data['answer']['state'] ?? '');
                    $detail['policy_state'] = $state;
                    if (in_array($state, ['eol', 'eol_grace_expired'], true)) {
                        $flagged = true;
                    }
                    break;
            }
        }

        $outcome = $flagged ? self::OUTCOME_FLAGGED : self::OUTCOME_PASS;

        return $this->logLine($unit, $unitCommit, $started, $outcome, $command, $exitCode, $detail);
    }

    /**
     * @param resource|null $process
     * @param array{0: resource, 1: resource, 2: resource}|null $pipes
     */
    private function closeServer($process, ?array $pipes): void
    {
        if ($pipes !== null) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }
        if ($process === null) {
            return;
        }
        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process);
            $grace = microtime(true) + 2;
            while (microtime(true) < $grace && proc_get_status($process)['running']) {
                usleep(50_000);
            }
            $status = proc_get_status($process);
        }
        if (!$status['running']) {
            $this->lastExit = $status['exitcode'];
        }
        proc_close($process);
    }

    /**
     * Starts `PHP_BINARY <advisor-bin> mcp` with the minimal environment the
     * server needs, pointed at one unit's scratch copy.
     *
     * @return array{0: resource, 1: array{0: resource, 1: resource, 2: resource}}
     */
    private function startServer(string $work): array
    {
        $env = [];
        $path = getenv('PATH');
        if (is_string($path) && $path !== '') {
            $env['PATH'] = $path;
        }
        $systemRoot = getenv('SystemRoot');
        if (is_string($systemRoot) && $systemRoot !== '') {
            $env['SystemRoot'] = $systemRoot;
        }
        $env += [
            'ADVISOR_ENABLED' => '1',
            'ADVISOR_ENV' => 'local',
            'ADVISOR_PROJECT_ROOT' => $work,
            'ADVISOR_BOOSTER_BIN' => $this->boosterBin,
            'ADVISOR_PROCTOR_TIMEOUT' => (string) min(900, $this->timeout),
            'ADVISOR_BOOSTER_TIMEOUT' => (string) min(900, $this->timeout),
        ];

        $pipes = [];
        $process = proc_open([PHP_BINARY, $this->advisorBin, 'mcp'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the advisor server.');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [$process, [0 => $pipes[0], 1 => $pipes[1], 2 => $pipes[2]]];
    }

    /**
     * @param resource $stdin
     * @param array<string, mixed> $message
     */
    private function send($stdin, array $message): void
    {
        $encoded = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Could not encode a protocol message.');
        }
        if (fwrite($stdin, $encoded . "\n") === false) {
            throw new RuntimeException('Could not write to the server input.');
        }
        fflush($stdin);
    }

    /**
     * Waits for the JSON-RPC response with the given id, watching stdout and
     * stderr until the deadline.
     *
     * @param resource $process
     * @param resource $stdout
     * @param resource $stderr
     * @return array<string, mixed>
     */
    private function expectResponse($process, $stdout, $stderr, int $id, float $deadline, string $what): array
    {
        while (true) {
            $message = $this->takeResponse($id);
            if ($message !== null) {
                return $message;
            }
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new RuntimeException("Timed out waiting for {$what}.");
            }
            $read = [$stdout, $stderr];
            $write = null;
            $except = null;
            $seconds = (int) floor($remaining);
            $micros = (int) round(($remaining - $seconds) * 1_000_000);
            if (stream_select($read, $write, $except, $seconds, $micros) === false) {
                throw new RuntimeException('Could not watch the server output.');
            }
            foreach ($read as $stream) {
                $chunk = (string) fread($stream, 65536);
                if ($chunk === '') {
                    continue;
                }
                if ($stream === $stdout) {
                    $this->ingestLine($chunk);
                } else {
                    $this->appendStderr($chunk);
                }
            }
            if (proc_get_status($process)['running'] === false) {
                while (($tail = (string) fread($stdout, 65536)) !== '') {
                    $this->ingestLine($tail);
                }
                $message = $this->takeResponse($id);
                if ($message !== null) {
                    return $message;
                }
                throw new RuntimeException("The server stopped before answering {$what}." . $this->stderrTail());
            }
        }
    }

    private function ingestLine(string $chunk): void
    {
        $this->stdoutBuffer .= $chunk;
        $parts = explode("\n", $this->stdoutBuffer);
        $this->stdoutBuffer = (string) array_pop($parts);
        foreach ($parts as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded) || !isset($decoded['jsonrpc'])) {
                $this->strayLines[] = substr($line, 0, 200);
                continue;
            }
            $id = $decoded['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $this->responses[$id][] = $decoded;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function takeResponse(int $id): ?array
    {
        $queue = $this->responses[$id] ?? null;
        if ($queue === null || $queue === []) {
            return null;
        }
        $message = array_shift($queue);
        $this->responses[$id] = $queue;

        return $message;
    }

    private function appendStderr(string $chunk): void
    {
        $this->stderrBuffer .= $chunk;
        if (strlen($this->stderrBuffer) > self::MAX_STDERR_TAIL * 2) {
            $this->stderrBuffer = substr($this->stderrBuffer, -self::MAX_STDERR_TAIL);
        }
    }

    private function stderrTail(): string
    {
        $tail = substr($this->stderrBuffer, -self::MAX_STDERR_TAIL);
        $tail = trim((string) preg_replace('/\s+/u', ' ', $tail));

        return $tail === '' ? '' : ' Server said: ' . $tail;
    }

    /**
     * Closes the input and waits for the server to end on its own.
     *
     * @param resource $process
     * @param resource $stdout
     * @param resource $stderr
     */
    private function waitForExit($process, $stdout, $stderr): int
    {
        $grace = microtime(true) + 10;
        while (microtime(true) < $grace) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                return $status['exitcode'];
            }
            $read = [$stdout, $stderr];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 100_000) !== false) {
                foreach ($read as $stream) {
                    $chunk = (string) fread($stream, 65536);
                    if ($chunk === '') {
                        continue;
                    }
                    if ($stream === $stdout) {
                        $this->ingestLine($chunk);
                    } else {
                        $this->appendStderr($chunk);
                    }
                }
            }
        }

        throw new RuntimeException('The server did not exit after the input closed.');
    }

    /**
     * Splits a tools/call answer into ok, refused or a tool error. A crash
     * wears the generic line from ToolInvoker; everything else with isError
     * set is a deliberate refusal the model could learn from.
     *
     * @param array<string, mixed> $response
     * @return array{ok: bool, refused: bool, ms: int, data?: array<string, mixed>, refused_text?: string}
     */
    private function judgeCall(string $name, array $response, int $ms): array
    {
        if (isset($response['error'])) {
            throw new RuntimeException($name . ' returned a protocol error: ' . (string) ($response['error']['message'] ?? 'unknown'));
        }
        $result = $response['result'] ?? null;
        if (!is_array($result)) {
            throw new RuntimeException($name . ' returned no result.');
        }
        $result['isError'] ??= false;
        if ($result['isError'] === true) {
            $text = $this->resultText($result);
            if ($text === self::UNEXPECTED_ERROR_TEXT) {
                throw new RuntimeException($name . ' crashed unexpectedly; the details are in the local ADVISOR log.');
            }

            return ['ok' => false, 'refused' => true, 'ms' => $ms, 'refused_text' => substr($text, 0, self::REFUSAL_TEXT_CHARS)];
        }
        $data = $this->resultData($result);
        if ($data === null) {
            throw new RuntimeException($name . ' returned a result without its expected structure.');
        }

        return ['ok' => true, 'refused' => false, 'ms' => $ms, 'data' => $data];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resultText(array $result): string
    {
        $content = $result['content'] ?? null;
        if (is_array($content)) {
            foreach ($content as $item) {
                if (is_array($item) && ($item['type'] ?? null) === 'text' && isset($item['text']) && is_string($item['text'])) {
                    return $item['text'];
                }
            }
        }

        return '(no text)';
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    private function resultData(array $result): ?array
    {
        if (isset($result['structuredContent']) && is_array($result['structuredContent'])) {
            return $result['structuredContent'];
        }
        $text = $this->resultText($result);
        if ($text === '(no text)') {
            return null;
        }
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $response
     * @return list<string>
     */
    private function missingTools(array $response): array
    {
        if (isset($response['error'])) {
            throw new RuntimeException('tools/list returned a protocol error: ' . (string) ($response['error']['message'] ?? 'unknown'));
        }
        $result = $response['result'] ?? null;
        $tools = is_array($result) && is_array($result['tools'] ?? null) ? $result['tools'] : [];
        $names = [];
        foreach ($tools as $tool) {
            if (is_array($tool) && isset($tool['name']) && is_string($tool['name'])) {
                $names[$tool['name']] = true;
            }
        }
        $missing = [];
        foreach (self::REQUIRED_TOOLS as $required) {
            if (!isset($names[$required])) {
                $missing[] = $required;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateProctorData(array $data): ?string
    {
        if (!isset($data['result']) || !in_array($data['result'], ['pass', 'fail'], true)) {
            return 'run_proctor_check returned a result without its expected structure.';
        }
        if (!is_array($data['findings'] ?? null)) {
            return 'run_proctor_check returned a result without its expected structure.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateBoosterData(array $data): ?string
    {
        if (!is_array($data['steps'] ?? null) || !is_array($data['warnings'] ?? null)) {
            return 'upgrade_path returned a result without its expected structure.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validatePolicyData(array $data): ?string
    {
        if (!is_array($data['answer'] ?? null) || !isset($data['answer']['state'])) {
            return 'policy_lookup returned a result without its expected structure.';
        }

        return null;
    }

    /**
     * Copies the unit into the scratch folder, leaving secrets, git history
     * and symlinks behind.
     */
    private function copyRecursive(string $from, string $to): void
    {
        if (!is_dir($from)) {
            throw new RuntimeException('The unit directory disappeared before the copy.');
        }
        if (!mkdir($to, 0775, true) && !is_dir($to)) {
            throw new RuntimeException('The scratch folder could not be created.');
        }
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $source = $from . '/' . $entry;
            if (is_link($source) || $this->skipName($entry)) {
                continue;
            }
            $target = $to . '/' . $entry;
            if (is_dir($source)) {
                $this->copyRecursive($source, $target);
            } elseif (!copy($source, $target)) {
                throw new RuntimeException('The copy of the unit failed on ' . $entry . '.');
            }
        }
    }

    private function skipName(string $name): bool
    {
        if (in_array($name, ['.git', 'auth.json', 'config.files'], true)) {
            return true;
        }

        return $name === '.env' || (str_starts_with($name, '.env.') && $name !== '.env.example');
    }

    private function hasPhpFile(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_link($path)) {
                continue;
            }
            if (is_dir($path)) {
                if ($this->hasPhpFile($path)) {
                    return true;
                }
                continue;
            }
            if (str_ends_with(strtolower($entry), '.php')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Points ADVISOR at a PROCTOR "inside the project" by laying a shim in
     * vendor/bin that only requires the operator's proctor binary.
     */
    private function ensureProctorShim(string $work): void
    {
        $shim = $work . '/vendor/bin/proctor';
        if (is_file($shim)) {
            return;
        }
        if (!mkdir(dirname($shim), 0775, true) && !is_dir(dirname($shim))) {
            throw new RuntimeException('The vendor/bin folder for the PROCTOR shim could not be created.');
        }
        $code = "<?php\n\nrequire " . var_export($this->proctorBin, true) . ";\n";
        if (file_put_contents($shim, $code) === false) {
            throw new RuntimeException('The PROCTOR shim could not be written.');
        }
    }

    /**
     * Removes the scratch copy of a unit.
     */
    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }

    /**
     * The HEAD sha of the unit's own git history, first 12 chars, or null.
     */
    private function gitHead(string $dir): ?string
    {
        if (!file_exists($dir . '/.git')) {
            return null;
        }
        $process = new Process(['git', '-C', $dir, 'rev-parse', 'HEAD'], null, null, null, 5.0);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        $sha = trim($process->getOutput());

        return preg_match('/^[0-9a-f]{40}$/', $sha) === 1 ? substr($sha, 0, 12) : null;
    }

    /**
     * The first major.minor PHP version the unit's composer.json asks for,
     * so policy_lookup can say whether it is still supported.
     */
    private function composerPhpVersion(string $work): ?string
    {
        $file = $work . '/composer.json';
        if (!is_file($file)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded) || !is_array($decoded['require'] ?? null)) {
            return null;
        }
        $requirePhp = $decoded['require']['php'] ?? null;
        if (!is_string($requirePhp)) {
            return null;
        }

        return preg_match('/(\d+\.\d+)/', $requirePhp, $match) === 1 ? $match[1] : null;
    }

    /**
     * Keeps paths out of anything the log may carry: the tool binaries become
     * their file names, and the scratch folder and the corpus both become /app.
     *
     * @param array{id: string, dir: string, kind: string} $unit
     */
    private function sanitize(string $text, array $unit, string $work): string
    {
        foreach ([$this->proctorBin, $this->boosterBin, $this->advisorBin] as $bin) {
            $text = str_replace([$bin, str_replace('/', '\\', $bin)], basename($bin), $text);
        }
        foreach ([$work, $unit['dir'], $this->workDir, $this->corpusDir] as $path) {
            $text = str_replace([$path, str_replace('/', '\\', $path)], '/app', $text);
        }

        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    /**
     * @param array{id: string, dir: string, kind: string} $unit
     */
    private function buildError(string $message, array $unit, string $work): string
    {
        $error = $this->sanitize($message, $unit, $work);
        if ($this->stderrBuffer !== '' && !str_contains($error, 'Server said')) {
            $error .= $this->sanitize($this->stderrTail(), $unit, $work);
        }

        return substr($error, 0, self::ERROR_TEXT_CHARS);
    }

    /**
     * @param array{id: string, dir: string, kind: string} $unit
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private function logLine(array $unit, ?string $unitCommit, float $started, string $outcome, string $command, ?int $exit, array $detail): array
    {
        return [
            'project' => Advisor::NAME,
            'tool_version' => Advisor::VERSION,
            'tool_commit' => $this->toolCommit,
            'unit_id' => $unit['id'],
            'unit_kind' => $unit['kind'],
            'unit_commit' => $unitCommit,
            'command' => $command,
            'image' => 'php ' . PHP_VERSION,
            'exit' => $exit,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'outcome' => $outcome,
            'detail' => $detail,
            'date' => $this->date,
        ];
    }
}
