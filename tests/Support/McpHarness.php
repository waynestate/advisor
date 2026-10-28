<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use Mcp\Server\Transport\StdioTransport;
use Psr\Log\NullLogger;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Server\SdkServer;

/**
 * An in-process MCP client. It writes JSON-RPC lines into a stream, runs the
 * real SDK server over the real stdio transport on that stream, and parses what
 * the server wrote back. No subprocess, no sockets.
 */
final class McpHarness
{
    private int $nextId = 1;

    /** @var list<string> */
    private array $lines = [];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $responses = null;

    private string $raw = '';

    public function __construct(private readonly Booted $booted, private readonly bool $modern = false) {}

    /**
     * Queue a request; returns its id.
     *
     * @param array<string, mixed> $params
     */
    public function request(string $method, array $params = []): int
    {
        $id = $this->nextId++;
        if ($this->modern) {
            $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientInfo' => ['name' => 'phpunit', 'version' => '1'], 'io.modelcontextprotocol/clientCapabilities' => new \stdClass()] + ($params['_meta'] ?? []);
        }
        $this->lines[] = (string) json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params === [] ? new \stdClass() : $params]);

        return $id;
    }

    /**
     * Send a raw line, for protocol-abuse tests.
     */
    public function raw(string $line): void
    {
        $this->lines[] = $line;
    }

    /** The handshake every legacy client opens with. */
    public function initialize(): int
    {
        $id = $this->request('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
        ]);
        $this->lines[] = (string) json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        return $id;
    }

    /**
     * Run the server over everything queued and return the responses by id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function run(): array
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');
        if ($input === false || $output === false) {
            throw new \RuntimeException('Cannot open memory streams.');
        }
        fwrite($input, implode("\n", $this->lines) . "\n");
        rewind($input);

        $logger = new NullLogger();
        SdkServer::build($this->booted, $logger)->run(new StdioTransport($input, $output, $logger));

        rewind($output);
        $this->raw = (string) stream_get_contents($output);
        $this->responses = [];
        foreach (explode("\n", trim($this->raw)) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded['id']) && is_int($decoded['id'])) {
                $this->responses[$decoded['id']] = $decoded;
            }
        }
        $this->lines = [];

        return $this->responses;
    }

    /** Everything the server wrote, exactly. */
    public function rawOutput(): string
    {
        return $this->raw;
    }

    /**
     * One-shot helper: initialize (unless modern), send one request, return its response.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        if (!$this->modern) {
            $this->initialize();
        }
        $id = $this->request($method, $params);
        $responses = $this->run();

        return $responses[$id] ?? throw new \RuntimeException("No response to {$method}. Output: " . $this->raw);
    }
}
