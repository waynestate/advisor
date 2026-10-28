<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Security;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Exception\ToolInputError;
use Waynestate\Advisor\Security\PathGuard;
use Waynestate\Advisor\Tests\Support\TempDir;

final class PathGuardTest extends TestCase
{
    use TempDir;

    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        $base = $this->tempDir();
        $this->root = $base . '/project';
        $this->outside = $base . '/secrets';
        $this->put($this->root . '/app/composer.json', '{}');
        $this->put($this->outside . '/id_rsa', 'not a real key');
    }

    public function testResolvesInsideThePath(): void
    {
        $guard = new PathGuard($this->root);

        $this->assertSame(realpath($this->root . '/app'), $guard->resolve('app'));
        $this->assertSame(realpath($this->root), $guard->resolve('.'));
        $this->assertSame('app', $guard->relative($guard->resolve('app')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return [
            'parent segment' => ['../secrets/id_rsa'],
            'nested parent' => ['app/../../secrets/id_rsa'],
            'backslash parent' => ['app\\..\\..\\secrets'],
            'home' => ['~/.ssh/id_rsa'],
            'null byte' => ["app\0/etc"],
            'empty' => [''],
            'absolute elsewhere' => ['/etc/passwd'],
            'missing' => ['nope/nothing'],
            'too long' => [str_repeat('a/', 600)],
        ];
    }

    #[DataProvider('hostile')]
    public function testRefusesHostilePaths(string $path): void
    {
        $this->expectException(ToolInputError::class);
        (new PathGuard($this->root))->resolve($path);
    }

    public function testRefusesAnAbsolutePathToASiblingFolderWithTheSamePrefix(): void
    {
        // "/tmp/x/project-evil" starts with "/tmp/x/project" but is not inside it.
        $this->put($this->root . '-evil/file', 'x');

        $this->expectException(ToolInputError::class);
        (new PathGuard($this->root))->resolve($this->root . '-evil/file');
    }

    public function testRefusesASymlinkThatLeavesTheProject(): void
    {
        if (!@symlink($this->outside, $this->root . '/link')) {
            $this->markTestSkipped('This system will not create symlinks.');
        }

        $this->expectException(ToolInputError::class);
        (new PathGuard($this->root))->resolve('link/id_rsa');
    }

    public function testErrorsNeverShowTheHostLayout(): void
    {
        try {
            (new PathGuard($this->root))->resolve('missing');
            $this->fail('Expected a refusal.');
        } catch (ToolInputError $e) {
            $this->assertStringNotContainsString($this->root, $e->getMessage());
        }
    }

    public function testRootMustBeADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PathGuard($this->root . '/app/composer.json');
    }
}
