<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;

/** Who owns a topic in the stack: one project runs it, the others help around it. */
final class OwnerOf
{
    public static function definition(ModuleContext $context): ToolDefinition
    {
        return new ToolDefinition(
            name: 'owner_of',
            title: 'Find the owner of a topic',
            description: 'Answers "which project owns X?" from ADVISOR\'s bundled ownership table. Fuzzy match: every word of the topic must appear in a row\'s topic or role (case-insensitive). It returns the matching rows (the owner, its role, and the projects that help around it), or an empty list with every known topic when nothing matches. Read-only; it names its source and date.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'topic' => ['type' => 'string', 'maxLength' => 200, 'description' => 'What you want to know the owner of, for example "composer audit".'],
                ],
                'required' => ['topic'],
                'additionalProperties' => false,
            ],
            handler: static function (array $args) use ($context): array {
                $topic = trim((string) ($args['topic'] ?? ''));
                if ($topic === '') {
                    throw new ToolInputError('Give a topic to look up, for example "composer audit".');
                }
                $words = array_values(array_filter(
                    preg_split('/[^a-z0-9]+/', strtolower($topic)) ?: [],
                    static fn(string $word): bool => $word !== '',
                ));
                if ($words === []) {
                    throw new ToolInputError('The topic has no words to match. Use letters or digits, for example "composer audit".');
                }

                $rows = $context->content->ownership();
                $matches = [];
                foreach ($rows as $row) {
                    $haystack = strtolower(((string) ($row['topic'] ?? '')) . ' ' . ((string) ($row['role'] ?? '')));
                    foreach ($words as $word) {
                        if (!str_contains($haystack, $word)) {
                            continue 2;
                        }
                    }
                    $matches[] = [
                        'topic' => (string) $row['topic'],
                        'owner' => (string) $row['owner'],
                        'role' => (string) $row['role'],
                        'also' => is_array($row['also'] ?? null) ? array_values($row['also']) : [],
                    ];
                }

                $answer = [
                    'source' => $context->content->source('ownership.json'),
                    'generated_on' => $context->content->generatedOn(),
                    'topic' => $topic,
                    'matches' => $matches,
                ];
                if ($matches === []) {
                    $answer['known_topics'] = array_map(static fn(array $row): string => (string) $row['topic'], $rows);
                    $answer['note'] = 'No match: every word of the topic must appear in a row\'s topic or role. Here are the known topics.';
                }

                return $answer;
            },
            group: 'stack',
            readOnly: true,
            idempotent: true,
            openWorld: false,
        );
    }
}
