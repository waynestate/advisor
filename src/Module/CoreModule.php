<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Content\RuleExtractor;
use Waynestate\Advisor\Tools\GetProjectStatus;
use Waynestate\Advisor\Tools\ListProjects;
use Waynestate\Advisor\Tools\ListStackRules;
use Waynestate\Advisor\Tools\OwnerOf;
use Waynestate\Advisor\Tools\PlaybookLookup;
use Waynestate\Advisor\Tools\PolicyLookup;
use Waynestate\Advisor\Tools\ReadProjectDocs;
use Waynestate\Advisor\Tools\RunProctorCheck;
use Waynestate\Advisor\Tools\UpgradePath;

/**
 * ADVISOR's own module: the nine read-only tools of 0.1, the CREED handbook
 * and policy as resources, and two prompts. Per-project modules (PROCTOR's
 * own tools, BOOSTER's upgrade planner) arrive through the manifest.
 */
final class CoreModule implements Module
{
    public function __construct(private readonly ModuleContext $context) {}

    public function id(): string
    {
        return 'core';
    }

    public function tools(): array
    {
        return [
            GetProjectStatus::definition($this->context),
            ListProjects::definition($this->context),
            ListStackRules::definition($this->context),
            OwnerOf::definition($this->context),
            PlaybookLookup::definition($this->context),
            PolicyLookup::definition($this->context),
            ReadProjectDocs::definition($this->context),
            RunProctorCheck::definition($this->context),
            UpgradePath::definition($this->context),
        ];
    }

    public function resources(): array
    {
        $content = $this->context->content;
        $resources = [
            new ResourceDefinition(
                uri: 'advisor://policy',
                name: 'policy',
                title: 'CREED policy.json',
                description: 'Support dates for PHP, Laravel, Node, MySQL, Vue and Inertia, from CREED, as bundled with ADVISOR.',
                mimeType: 'application/json',
                read: static fn(): string => (string) file_get_contents($content->policyFile()),
            ),
        ];
        foreach ($content->handbookChapters() as $chapter) {
            $id = $chapter['id'];
            $resources[] = new ResourceDefinition(
                uri: 'advisor://creed/' . $id,
                name: 'creed-' . $id,
                title: 'CREED handbook: ' . $chapter['title'],
                description: 'The CREED handbook chapter "' . $chapter['title'] . '", from ' . $content->source('handbook/' . $id . '.md') . '.',
                mimeType: 'text/markdown',
                read: static fn(): string => $content->handbook($id) ?? '',
            );
        }

        return $resources;
    }

    public function prompts(): array
    {
        return [
            new PromptDefinition(
                name: 'orient_me',
                title: 'Orient me in the WSU stack',
                description: 'Walks through which stack projects exist, which CREED rules apply to your stack, whether your versions are supported and what to do next.',
                arguments: [['name' => 'permutation', 'description' => 'One of ' . implode(', ', RuleExtractor::PERMUTATIONS) . '. Default: all.', 'required' => false]],
                render: static fn(array $args): string => self::template('orient-me', [
                    'permutation' => in_array($args['permutation'] ?? '', RuleExtractor::PERMUTATIONS, true) ? $args['permutation'] : 'not stated (ask the developer, or look at composer.json and package.json)',
                ]),
            ),
            new PromptDefinition(
                name: 'plan_upgrade',
                title: 'Plan the next upgrade hop',
                description: 'Plans a PHP or Laravel upgrade one supported hop at a time, using the policy, PROCTOR and the upgrade tool.',
                arguments: [['name' => 'target', 'description' => 'Target version, for example 8.4. Default: the next supported one.', 'required' => false]],
                render: static fn(array $args): string => self::template('plan-upgrade', [
                    'target' => preg_match('/^[0-9]{1,3}(\.[0-9]{1,3}){0,2}$/', $args['target'] ?? '') ? $args['target'] : 'the next supported version',
                ]),
            ),
        ];
    }

    /**
     * @param array<string, string> $values already validated against a fixed list or pattern
     */
    private static function template(string $name, array $values): string
    {
        $text = (string) file_get_contents(Advisor::promptsDir() . '/' . $name . '.md');
        foreach ($values as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }
}
