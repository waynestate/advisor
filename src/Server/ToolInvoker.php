<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Server;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;
use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Advisor\Support\Invisible;

/**
 * Runs a tool the same way for every adapter: validate the arguments a second
 * time (the client's schema check is guidance, not a guarantee), run the
 * handler, turn a model-fixable problem into an error result, clean the
 * output of invisible characters, and write the audit line.
 */
final class ToolInvoker
{
    /** Hard ceiling on one result's text, whatever a tool returns. */
    private const MAX_RESULT_CHARS = 100000;

    private readonly Validator $validator;

    public function __construct(
        private readonly AuditLog $audit,
        private readonly LoggerInterface $logger,
    ) {
        $this->validator = new Validator();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function invoke(ToolDefinition $tool, array $arguments): ToolOutcome
    {
        $started = microtime(true);
        $outcome = $this->run($tool, $arguments);
        $this->audit->record($tool->name, $arguments, $outcome->isError ? 'error' : 'ok', (microtime(true) - $started) * 1000);

        return $outcome;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function run(ToolDefinition $tool, array $arguments): ToolOutcome
    {
        $problem = $this->validate($tool, $arguments);
        if ($problem !== null) {
            return new ToolOutcome(true, $problem);
        }

        try {
            $data = ($tool->handler)($arguments);
        } catch (ToolInputError $e) {
            return new ToolOutcome(true, self::clean($e->getMessage()));
        } catch (Throwable $e) {
            // The detail goes to the local log on stderr; the model gets a generic line.
            $this->logger->error('Tool failed: ' . $tool->name, ['exception' => $e]);

            return new ToolOutcome(true, 'The tool failed unexpectedly. The details are in the local ADVISOR log.');
        }

        $data = self::cleanValue($data);
        $text = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($text === false || strlen($text) > self::MAX_RESULT_CHARS) {
            return new ToolOutcome(true, 'The result was too large to return. Ask for a smaller part, for example with a doc, chapter or offset argument.');
        }

        /** @var array<string, mixed> $data */
        return new ToolOutcome(false, $text, $data);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function validate(ToolDefinition $tool, array $arguments): ?string
    {
        $value = json_decode((string) json_encode($arguments === [] ? new stdClass() : $arguments));
        $schema = json_decode((string) json_encode($tool->inputSchema));
        $result = $this->validator->validate($value, $schema);
        if ($result->isValid()) {
            return null;
        }
        $error = $result->error();
        $messages = $error === null ? [] : (new ErrorFormatter())->flatten($error);

        return 'Invalid arguments for ' . $tool->name . ': ' . self::clean(implode('; ', array_slice($messages, 0, 3)));
    }

    private static function clean(string $text): string
    {
        return Invisible::strip($text);
    }

    private static function cleanValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return Invisible::strip($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[is_string($key) ? Invisible::strip($key) : $key] = self::cleanValue($item);
            }

            return $out;
        }

        return $value;
    }
}
