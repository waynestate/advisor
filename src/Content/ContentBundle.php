<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Content;

use Waynestate\Advisor\Exception\ContentException;
use Waynestate\Advisor\Support\Invisible;
use Waynestate\Advisor\Support\Json;

/**
 * The content ADVISOR serves, read from the bundled `data/` folder.
 *
 * The folder is generated from the sibling stack repositories (see Generator)
 * and described by a manifest of SHA-256 hashes. verify() runs before the
 * first read and fails closed: a changed, missing or extra file, or any
 * invisible character, and nothing is served (best practices 3.5 and 2.10).
 */
final class ContentBundle
{
    public const MANIFEST = 'manifest.json';

    public const DOCS = ['readme' => 'md', 'best-practices' => 'md', 'contract' => 'json'];

    private bool $verified = false;

    /** @var array<mixed>|null */
    private ?array $manifest = null;

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly string $dir) {}

    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * @throws ContentException
     */
    public function verify(): void
    {
        $problems = $this->problems();
        if ($problems !== []) {
            throw new ContentException("The bundled content failed its check:\n- " . implode("\n- ", $problems));
        }
        $this->verified = true;
    }

    /**
     * @return list<string> one line per problem; empty means the bundle is sound
     */
    public function problems(): array
    {
        $problems = [];
        $manifestPath = $this->dir . '/' . self::MANIFEST;
        if (!is_file($manifestPath)) {
            return ['data/manifest.json is missing. Run `composer content:generate`.'];
        }

        try {
            $manifest = Json::decodeFile($manifestPath);
        } catch (\RuntimeException $e) {
            return [$e->getMessage()];
        }
        $files = $manifest['files'] ?? null;
        if (($manifest['schema_version'] ?? null) !== 1 || !is_array($files)) {
            return ['data/manifest.json has an unsupported shape.'];
        }

        $listed = [];
        foreach ($files as $relative => $entry) {
            $relative = (string) $relative;
            $listed[$relative] = true;
            if (!self::safeRelative($relative) || !is_array($entry) || !is_string($entry['sha256'] ?? null)) {
                $problems[] = "The manifest entry {$relative} is not valid.";
                continue;
            }
            $path = $this->dir . '/' . $relative;
            if (!is_file($path)) {
                $problems[] = "{$relative} is listed in the manifest but missing.";
                continue;
            }
            $raw = (string) file_get_contents($path);
            if (!hash_equals($entry['sha256'], hash('sha256', $raw))) {
                $problems[] = "{$relative} does not match its manifest hash.";
            }
            $bad = Invisible::find($raw);
            if ($bad !== []) {
                $problems[] = "{$relative} contains invisible or control characters: " . implode(', ', $bad) . '.';
            }
        }

        foreach ($this->filesOnDisk() as $relative) {
            if ($relative !== self::MANIFEST && !isset($listed[$relative])) {
                $problems[] = "{$relative} is on disk but not in the manifest.";
            }
        }

        return $problems;
    }

    /**
     * @return array<mixed>
     */
    public function manifest(): array
    {
        $this->ensureVerified();

        return $this->manifest ??= Json::decodeFile($this->dir . '/' . self::MANIFEST);
    }

    public function generatedOn(): string
    {
        $date = $this->manifest()['generated_on'] ?? '';

        return is_string($date) ? $date : '';
    }

    /**
     * Where a bundled file came from, for the "origin and date" on every answer.
     */
    public function source(string $relative): string
    {
        $files = $this->manifest()['files'] ?? [];
        $entry = is_array($files) ? ($files[$relative] ?? null) : null;

        return is_array($entry) && is_string($entry['source'] ?? null) ? $entry['source'] : $relative;
    }

    public function policyFile(): string
    {
        $this->ensureVerified();

        return $this->dir . '/policy.json';
    }

    public function policy(): PolicyReader
    {
        $this->ensureVerified();
        $cached = $this->cache['policy'] ?? null;
        if (!$cached instanceof PolicyReader) {
            $cached = PolicyReader::fromArray(Json::decodeFile($this->dir . '/policy.json'));
            $this->cache['policy'] = $cached;
        }

        return $cached;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function projects(): array
    {
        $this->ensureVerified();
        $cached = $this->cache['projects'] ?? null;
        if (!is_array($cached)) {
            $data = Json::decodeFile($this->dir . '/projects.json');
            $cached = is_array($data['projects'] ?? null) ? array_values($data['projects']) : [];
            $this->cache['projects'] = $cached;
        }

        /** @var list<array<string, mixed>> $cached */
        return $cached;
    }

    /**
     * @return list<string>
     */
    public function projectIds(): array
    {
        return array_map(static fn (array $p): string => (string) $p['id'], $this->projects());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function project(string $id): ?array
    {
        foreach ($this->projects() as $project) {
            if (($project['id'] ?? null) === $id) {
                return $project;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rules(): array
    {
        $this->ensureVerified();
        $cached = $this->cache['rules'] ?? null;
        if (!is_array($cached)) {
            $data = Json::decodeFile($this->dir . '/rules.json');
            $cached = is_array($data['rules'] ?? null) ? array_values($data['rules']) : [];
            $this->cache['rules'] = $cached;
        }

        /** @var list<array<string, mixed>> $cached */
        return $cached;
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public function handbookChapters(): array
    {
        $this->ensureVerified();
        $chapters = [];
        $files = glob($this->dir . '/handbook/*.md') ?: [];
        sort($files);
        foreach ($files as $file) {
            $id = basename($file, '.md');
            if ($id === 'README') {
                continue;
            }
            $first = strtok((string) file_get_contents($file), "\n");
            $title = is_string($first) && str_starts_with($first, '# ') ? trim(substr($first, 2)) : $id;
            $chapters[] = ['id' => $id, 'title' => $title];
        }

        return $chapters;
    }

    public function handbook(string $chapter): ?string
    {
        $this->ensureVerified();
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $chapter)) {
            return null;
        }
        $path = $this->dir . '/handbook/' . $chapter . '.md';

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * @return list<string> the doc kinds (readme, best-practices, contract) bundled for a project
     */
    public function docsFor(string $project): array
    {
        $this->ensureVerified();
        $available = [];
        foreach (self::DOCS as $doc => $extension) {
            if (preg_match('/^[a-z0-9-]{1,40}$/', $project) && is_file($this->docPath($project, $doc, $extension))) {
                $available[] = $doc;
            }
        }

        return $available;
    }

    public function doc(string $project, string $doc): ?string
    {
        $this->ensureVerified();
        $extension = self::DOCS[$doc] ?? null;
        if ($extension === null || !preg_match('/^[a-z0-9-]{1,40}$/', $project)) {
            return null;
        }
        $path = $this->docPath($project, $doc, $extension);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function docRelativePath(string $project, string $doc): string
    {
        return 'docs/' . $project . '/' . $doc . '.' . (self::DOCS[$doc] ?? 'md');
    }

    private function docPath(string $project, string $doc, string $extension): string
    {
        return $this->dir . '/docs/' . $project . '/' . $doc . '.' . $extension;
    }

    private function ensureVerified(): void
    {
        if (!$this->verified) {
            $this->verify();
        }
    }

    /**
     * @return list<string>
     */
    private function filesOnDisk(): array
    {
        $out = [];
        if (!is_dir($this->dir)) {
            return $out;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($this->dir) + 1));
            }
        }
        sort($out);

        return $out;
    }

    private static function safeRelative(string $relative): bool
    {
        return $relative !== '' && !str_contains($relative, '..') && !str_starts_with($relative, '/') && !str_contains($relative, '\\')
            && (bool) preg_match('#^[A-Za-z0-9._/-]+$#', $relative);
    }
}
