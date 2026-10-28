<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Security;

use InvalidArgumentException;
use Waynestate\Advisor\Exception\ToolInputError;

/**
 * Confines every path a tool is given to the project root (best practice 2.5).
 *
 * The path is resolved with symlinks followed, then compared with the resolved
 * root, so `../../.ssh/id_rsa` and a symlink pointing out of the project both
 * fail. It never says where the root is.
 */
final class PathGuard
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $resolved = realpath($root);
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('The ADVISOR project root is not a directory.');
        }
        $this->root = rtrim($resolved, '/\\');
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @throws ToolInputError when the path is empty, odd, missing, or outside the root
     */
    public function resolve(string $path): string
    {
        if ($path === '' || strlen($path) > 1024 || str_contains($path, "\0")) {
            throw new ToolInputError('The path is empty, too long, or contains a null byte. Give a path inside the project.');
        }
        if ($path[0] === '~') {
            throw new ToolInputError('Paths starting with ~ are not allowed. Give a path inside the project.');
        }
        foreach (preg_split('#[/\\\\]#', $path) ?: [] as $segment) {
            if ($segment === '..') {
                throw new ToolInputError('Paths containing .. are not allowed. Give a path inside the project.');
            }
        }

        $absolute = $this->isAbsolute($path) ? $path : $this->root . DIRECTORY_SEPARATOR . $path;
        $real = realpath($absolute);
        if ($real === false || !$this->isInside($real)) {
            // Same message for "missing" and "outside" so the answer does not map the disk.
            throw new ToolInputError('That path does not exist inside the project.');
        }

        return $real;
    }

    public function isInside(string $realPath): bool
    {
        $root = $this->root;
        if (PHP_OS_FAMILY === 'Windows') {
            $realPath = strtolower($realPath);
            $root = strtolower($root);
        }

        return $realPath === $root || str_starts_with($realPath, $root . DIRECTORY_SEPARATOR);
    }

    /** The path relative to the root, for output that must not show the host layout. */
    public function relative(string $realPath): string
    {
        if (!$this->isInside($realPath) || strlen($realPath) <= strlen($this->root)) {
            return '.';
        }

        return str_replace('\\', '/', substr($realPath, strlen($this->root) + 1));
    }

    private function isAbsolute(string $path): bool
    {
        return $path[0] === '/' || $path[0] === '\\' || (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
