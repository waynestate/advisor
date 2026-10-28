<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

use RuntimeException;
use Waynestate\Advisor\Support\Clock;
use Waynestate\Advisor\Support\Invisible;
use Waynestate\Advisor\Support\Json;

/**
 * Builds the bundled `data/` folder from the sibling stack repositories.
 *
 * ADVISOR depends on those repositories' content and does not copy by hand:
 * `content:generate` rewrites the folder, and `content:generate --check`
 * fails when the folder differs from what the sources would produce today.
 * The manifest holds a SHA-256 per file, a source path, and the day the
 * content last changed (not the day the generator last ran, so a re-run with
 * no changes is byte-identical).
 */
final class Generator
{
    private const MAX_FILE_BYTES = 1_000_000;

    /**
     * ADVISOR is a public repository, so the bundle must not carry internal
     * names. The sources are being cleaned for public release; until they are,
     * the bundle step replaces what is left with neutral wording. Each rule
     * names the source case it covers, and the public-posture test fails on
     * anything a rule misses. Private strings (hostnames, people) are loaded
     * from the file named by the ADVISOR_PRIVATE_DENYLIST environment variable.
     *
     * @var list<array{0: string, 1: string}> pattern, replacement
     */
    private const SANITIZATIONS = [
        // [text](../design/ADR/...) -> text
        ['#\[(?<text>[^\]]+)\]\(((?:\.\./)*)design/ADR/[^)\s]+\)#', '$1'],
        // `design/<name>.md` -> `a design document`
        ['#`design/[a-z-]+\.md`#', '`a design document`'],
        // design/<name>.md without backticks
        ['#design/[a-z-]+\.md#', 'a design document'],
        // [name@wayne.edu](mailto:...) -> the WayneUI maintainers
        ['#\[[^\]]+@wayne\.edu\]\(mailto:[^)]+\)#', 'the WayneUI maintainers'],
        // (D-xxx) -> removed
        ['#\s*\(\bD-\d{3}\b\)#', ''],
        // D-xxx -> an open stack question
        ['#\bD-\d{3}\b#', 'an open stack question'],
        // ADR-xxxx -> an ADR
        ['#\bADR-\d{4}\b#', 'an ADR'],
        // "the coordination repo[itory]" -> "the stack's design documents"
        ['#\b(?:the )?coordination repo(?:sitory)?#', "the stack's design documents"],
    ];

    private readonly Denylist $denylist;

    public function __construct(
        private readonly string $sourcesDir,
        private readonly string $outDir,
        private readonly string $seedFile,
        private readonly Clock $clock,
        ?Denylist $denylist = null,
    ) {
        $this->denylist = $denylist ?? Denylist::fromEnv();
    }

    /**
     * What the sources produce today, as relative path => [content, source].
     *
     * @return array<string, array{content: string, source: string}>
     */
    public function build(): array
    {
        $files = [];

        $policy = $this->read('creed/policy/policy.json');
        $files['policy.json'] = ['content' => $policy, 'source' => 'creed/policy/policy.json'];
        $policyData = Json::decode($policy, 'creed/policy/policy.json');
        PolicyReader::fromArray($policyData); // refuses a schema this build does not understand

        $rules = [];
        $handbookDir = $this->sourcesDir . '/creed/handbook';
        $chapterFiles = glob($handbookDir . '/*.md') ?: [];
        sort($chapterFiles);
        if ($chapterFiles === []) {
            throw new RuntimeException('No CREED handbook found at creed/handbook. Point --sources at the folder that holds the stack repositories.');
        }
        foreach ($chapterFiles as $file) {
            $name = basename($file, '.md');
            $content = $this->clean((string) file_get_contents($file));
            $files['handbook/' . $name . '.md'] = ['content' => $content, 'source' => 'creed/handbook/' . $name . '.md'];
            if ($name !== 'README') {
                array_push($rules, ...RuleExtractor::fromChapter($name, $content));
            }
        }
        foreach (($policyData['rules'] ?? []) as $key => $value) {
            $rules[] = [
                'id' => 'policy-' . $key,
                'chapter' => 'policy',
                'heading' => 'rules',
                'text' => sprintf('policy.json sets rules.%s to %s.', $key, json_encode($value, JSON_UNESCAPED_SLASHES)),
                'applies_to' => RuleExtractor::PERMUTATIONS,
                'source' => 'policy.json',
            ];
        }
        foreach (($policyData['tools'] ?? []) as $tool => $entry) {
            $rules[] = [
                'id' => 'policy-tool-' . $tool,
                'chapter' => 'policy',
                'heading' => 'tools',
                'text' => sprintf('policy.json sets the minimum %s version to %s.', $tool, is_array($entry) ? ($entry['minimum'] ?? 'unset') : 'unset'),
                'applies_to' => RuleExtractor::PERMUTATIONS,
                'source' => 'policy.json',
            ];
        }
        $files['rules.json'] = ['content' => Json::encode(['rules' => $rules]), 'source' => 'creed/handbook, creed/policy/policy.json'];

        $projects = [];
        foreach ($this->seed() as $seed) {
            [$entry, $docs] = $this->project($seed);
            $projects[] = $entry;
            foreach ($docs as $relative => $doc) {
                $files[$relative] = $doc;
            }
        }
        $files['projects.json'] = ['content' => Json::encode(['projects' => $projects]), 'source' => 'generator/projects.seed.json plus each project repository'];

        ksort($files);

        return $files;
    }

    /**
     * @return list<string> what differs from the bundle on disk; empty means up to date
     */
    public function check(): array
    {
        $diff = [];
        $built = $this->build();
        $existing = $this->existingHashes();
        foreach ($built as $relative => $file) {
            if (!isset($existing[$relative])) {
                $diff[] = "new: {$relative}";
            } elseif ($existing[$relative] !== hash('sha256', $file['content'])) {
                $diff[] = "changed: {$relative}";
            }
        }
        foreach (array_keys($existing) as $relative) {
            if (!isset($built[$relative])) {
                $diff[] = "removed: {$relative}";
            }
        }

        return $diff;
    }

    /**
     * Writes the bundle. Returns the same list check() would have returned
     * before the write, so the caller can say what changed.
     *
     * @return list<string>
     */
    public function generate(): array
    {
        $built = $this->build();
        $diff = $this->check();
        $manifestPath = $this->outDir . '/' . ContentBundle::MANIFEST;
        $previous = is_file($manifestPath) ? Json::decodeFile($manifestPath) : [];
        $generatedOn = is_string($previous['generated_on'] ?? null) ? $previous['generated_on'] : $this->clock->today()->format('Y-m-d');
        if ($diff !== []) {
            $generatedOn = $this->clock->today()->format('Y-m-d');
        }

        if (is_dir($this->outDir)) {
            $this->wipe($this->outDir);
        }
        $manifestFiles = [];
        foreach ($built as $relative => $file) {
            $path = $this->outDir . '/' . $relative;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                throw new RuntimeException("Cannot create the folder for {$relative}.");
            }
            file_put_contents($path, $file['content']);
            $manifestFiles[$relative] = ['sha256' => hash('sha256', $file['content']), 'source' => $file['source']];
        }
        file_put_contents($manifestPath, Json::encode([
            'schema_version' => 1,
            'generated_on' => $generatedOn,
            'files' => $manifestFiles,
        ]));

        return $diff;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seed(): array
    {
        $data = Json::decodeFile($this->seedFile);
        $projects = $data['projects'] ?? [];
        if (!is_array($projects)) {
            throw new RuntimeException('The project seed has no projects list.');
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values($projects);

        return $list;
    }

    /**
     * @param array<string, mixed> $seed
     *
     * @return array{0: array<string, mixed>, 1: array<string, array{content: string, source: string}>}
     */
    private function project(array $seed): array
    {
        $id = (string) $seed['id'];
        $dir = $this->sourcesDir . '/' . $id;
        $docs = [];

        $package = null;
        $php = null;
        $composer = $dir . '/composer.json';
        if (is_file($composer)) {
            $data = Json::decodeFile($composer);
            $package = is_string($data['name'] ?? null) ? $data['name'] : null;
            $require = is_array($data['require'] ?? null) ? $data['require'] : [];
            $php = is_string($require['php'] ?? null) ? $require['php'] : null;
        }
        $built = is_file($composer) || is_file($dir . '/package.json') || is_dir($dir . '/templates');

        $readme = $dir . '/README.md';
        if (is_file($readme)) {
            $docs["docs/{$id}/readme.md"] = ['content' => $this->clean((string) file_get_contents($readme)), 'source' => "{$id}/README.md"];
        }
        $bestPractices = $dir . '/BEST_PRACTICES.md';
        if (is_file($bestPractices)) {
            $docs["docs/{$id}/best-practices.md"] = ['content' => $this->clean((string) file_get_contents($bestPractices)), 'source' => "{$id}/BEST_PRACTICES.md"];
        }
        $contractRelative = is_string($seed['contract'] ?? null) ? $seed['contract'] : null;
        if ($contractRelative !== null && is_file($dir . '/' . $contractRelative)) {
            $docs["docs/{$id}/contract.json"] = ['content' => $this->clean((string) file_get_contents($dir . '/' . $contractRelative)), 'source' => "{$id}/{$contractRelative}"];
        }

        $entry = [
            'id' => $id,
            'name' => (string) $seed['name'],
            'kind' => (string) $seed['kind'],
            'tagline' => (string) $seed['tagline'],
            'role' => (string) $seed['role'],
            'state' => $built ? 'built' : 'proposed',
            'package' => $package,
            'php' => $php,
            'next_step' => (string) $seed['next_step'],
            'docs' => array_map(
                static fn(string $path): string => pathinfo($path, PATHINFO_FILENAME),
                array_keys($docs),
            ),
        ];

        return [$entry, $docs];
    }

    private function read(string $relative): string
    {
        $path = $this->sourcesDir . '/' . $relative;
        if (!is_file($path)) {
            throw new RuntimeException("Source file not found: {$relative}. Point --sources at the folder that holds the stack repositories.");
        }

        return $this->clean((string) file_get_contents($path));
    }

    /** LF line endings, one final newline, no invisible characters, no internal names, a size cap. */
    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = Invisible::strip($text);
        $text = $this->sanitize($text);
        if (strlen($text) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('A source file is larger than 1 MB; refusing to bundle it.');
        }

        return rtrim($text, "\n") . "\n";
    }

    /** Replaces the internal names the sources still carry (see SANITIZATIONS and the private denylist). */
    private function sanitize(string $text): string
    {
        foreach (self::SANITIZATIONS as [$pattern, $replacement]) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }
        if (!$this->denylist->isEmpty()) {
            $text = $this->denylist->replace($text);
        }

        return $text;
    }

    /**
     * @return array<string, string> relative path => sha256 from the manifest on disk
     */
    private function existingHashes(): array
    {
        $manifestPath = $this->outDir . '/' . ContentBundle::MANIFEST;
        if (!is_file($manifestPath)) {
            return [];
        }
        $out = [];
        $files = Json::decodeFile($manifestPath)['files'] ?? [];
        foreach (is_array($files) ? $files : [] as $relative => $entry) {
            if (is_array($entry) && is_string($entry['sha256'] ?? null)) {
                // Compare what is actually on disk, not only what the manifest claims.
                $path = $this->outDir . '/' . $relative;
                $out[(string) $relative] = is_file($path) ? hash('sha256', (string) file_get_contents($path)) : '';
            }
        }

        return $out;
    }

    private function wipe(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
