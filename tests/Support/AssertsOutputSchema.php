<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;
use Waynestate\Advisor\Module\ToolDefinition;
use Waynestate\Advisor\Server\ToolOutcome;

/**
 * The MCP spec says a tool that declares an output schema must return
 * structured content that conforms to it. Every successful call a test makes
 * goes through here, so a handler and its schema cannot drift apart quietly.
 * (A schema that only says "object" passes everything, which is why the
 * contract test also refuses a bare one.)
 */
trait AssertsOutputSchema
{
    protected function assertConformsToOutputSchema(ToolDefinition $tool, ToolOutcome $outcome): void
    {
        if ($outcome->isError) {
            return;
        }

        // Validate the bytes a client receives: the data as JSON, decoded the way a client decodes it.
        $value = json_decode((string) json_encode($outcome->data === [] ? new stdClass() : $outcome->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        $schema = json_decode((string) json_encode($tool->outputSchema));
        $result = (new Validator())->validate($value, $schema);

        $messages = [];
        $error = $result->error();
        if ($error !== null) {
            foreach ((new ErrorFormatter())->format($error) as $path => $lines) {
                foreach ((array) $lines as $line) {
                    $messages[] = $path . ': ' . $line;
                }
            }
        }

        $this->assertTrue(
            $result->isValid(),
            $tool->name . ' returned structured content that does not match its outputSchema: ' . implode('; ', array_slice($messages, 0, 5)),
        );
    }
}
