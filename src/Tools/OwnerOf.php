<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tools;

use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Module\ModuleContext;
use Waynestate\Advisor\Module\ToolDefinition;

/**
 * Who owns a topic in the stack: one project runs it, the others help around it.
 *
 * Every row names the decision it comes from, so an answer can be checked. A
 * student asks their advisor "who signs off on this?" and gets a name and the
 * catalog page, not a shrug.
 */
final class OwnerOf
{
    /**
     * Question words that say nothing about the topic, so "who owns composer
     * audit" matches the same rows as "composer audit".
     */
    private const FILLER = [
        'a', 'an', 'and', 'are', 'did', 'do', 'does', 'for', 'in', 'is', 'it', 'of', 'on', 'own', 'owned', 'owner',
        'owners', 'owns', 'responsible', 'the', 'to', 'what', 'which', 'who', 'whom', 'whose',
    ];

    public static function definition(ModuleContext $context): ToolDefinition
    {
        return new ToolDefinition(
            name: 'owner_of',
            title: 'Find the owner of a topic',
            description: 'Answers "which project owns X?" from ADVISOR\'s bundled ownership table, built from the stack\'s one-owner-per-rule decision and the decisions after it. Pass the topic or the whole question ("who owns composer audit"); question words are ignored. Every remaining word must appear in a row\'s topic, aliases or role (case-insensitive); exact topic or alias matches come first, then topic and alias matches, then role matches. Each match returns the owner, its role, the projects that help around it, and the decision it comes from; when nothing matches, an empty list and every known topic. Read-only; it names its source and date.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'topic' => ['type' => 'string', 'maxLength' => 200, 'description' => 'What you want to know the owner of, for example "composer audit" or "who owns the /up endpoint?".'],
                ],
                'required' => ['topic'],
                'additionalProperties' => false,
            ],
            outputSchema: self::outputSchema(),
            handler: static function (array $args) use ($context): array {
                $topic = trim((string) ($args['topic'] ?? ''));
                if ($topic === '') {
                    throw new ToolInputError('Give a topic to look up, for example "composer audit".');
                }
                $words = self::words($topic);
                if ($words === []) {
                    throw new ToolInputError('The topic has no words to match. Use letters or digits, for example "composer audit".');
                }

                $rows = $context->content->ownership();
                $ranked = [];
                foreach ($rows as $index => $row) {
                    $rank = self::rank($words, $row);
                    if ($rank === 0) {
                        continue;
                    }
                    $ranked[] = [$rank, $index, [
                        'topic' => (string) $row['topic'],
                        'owner' => (string) $row['owner'],
                        'role' => (string) $row['role'],
                        'also' => is_array($row['also'] ?? null) ? array_values($row['also']) : [],
                        'decision' => (string) ($row['decision'] ?? ''),
                    ]];
                }
                // Best rank first; within a rank, the table's own order (the decisions come first there).
                usort($ranked, static fn(array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
                $matches = array_map(static fn(array $entry): array => $entry[2], $ranked);

                $answer = [
                    'source' => $context->content->source('ownership.json'),
                    'generated_on' => $context->content->generatedOn(),
                    'topic' => $topic,
                    'matches' => $matches,
                ];
                if ($matches === []) {
                    $answer['known_topics'] = array_map(static fn(array $row): string => (string) $row['topic'], $rows);
                    $answer['note'] = 'No match: every word of the topic must appear in a row\'s topic, aliases or role. Here are the known topics.';
                }

                return $answer;
            },
            group: 'stack',
            readOnly: true,
            idempotent: true,
            openWorld: false,
        );
    }

    /**
     * What owner_of returns: the matching rows, or every known topic when nothing matches.
     *
     * @return array<string, mixed>
     */
    private static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'source' => ['type' => 'string', 'description' => 'Where the ownership table came from.'],
                'generated_on' => ['type' => 'string', 'description' => 'The date the bundled content was generated, YYYY-MM-DD.'],
                'topic' => ['type' => 'string', 'description' => 'The topic as asked, trimmed.'],
                'matches' => [
                    'type' => 'array',
                    'description' => 'The rows that hold every word of the topic, best match first.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'topic' => ['type' => 'string', 'description' => 'The topic the row covers.'],
                            'owner' => ['type' => 'string', 'description' => 'The project that owns the topic.'],
                            'role' => ['type' => 'string', 'description' => 'What the owner does for the topic.'],
                            'also' => [
                                'type' => 'array',
                                'description' => 'The projects that help around the topic.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'project' => ['type' => 'string', 'description' => 'The helping project.'],
                                        'role' => ['type' => 'string', 'description' => 'What the project does around the topic.'],
                                    ],
                                ],
                            ],
                            'decision' => ['type' => 'string', 'description' => 'The decision the row comes from.'],
                        ],
                    ],
                ],
                'known_topics' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Every topic in the table, present when nothing matched.'],
                'note' => ['type' => 'string', 'description' => 'A hint for when nothing matched.'],
            ],
            'required' => ['source', 'generated_on', 'topic', 'matches'],
        ];
    }

    /**
     * The topic's words, lower-cased, without the question words.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($text)) ?: [];

        return array_values(array_filter(
            $words,
            static fn(string $word): bool => $word !== '' && !in_array($word, self::FILLER, true),
        ));
    }

    /**
     * 3 when the words are exactly a topic or an alias, 2 when every word is in
     * the topic or aliases, 1 when every word is somewhere in the row (role
     * included), 0 when a word is missing.
     *
     * @param list<string> $words
     * @param array<string, mixed> $row
     */
    private static function rank(array $words, array $row): int
    {
        $names = [(string) ($row['topic'] ?? '')];
        foreach (is_array($row['aliases'] ?? null) ? $row['aliases'] : [] as $alias) {
            $names[] = (string) $alias;
        }

        $wanted = implode(' ', $words);
        foreach ($names as $name) {
            if (implode(' ', self::words($name)) === $wanted) {
                return 3;
            }
        }

        $nameText = strtolower(implode(' ', $names));
        if (self::containsAll($nameText, $words)) {
            return 2;
        }

        return self::containsAll($nameText . ' ' . strtolower((string) ($row['role'] ?? '')), $words) ? 1 : 0;
    }

    /**
     * Every word must start a word in the haystack, so "lock" finds "lock file"
     * and "upgrade" finds "upgrades", but "it" does not find "audit".
     *
     * @param list<string> $words
     */
    private static function containsAll(string $haystack, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($word, '/') . '/', $haystack) !== 1) {
                return false;
            }
        }

        return true;
    }
}
