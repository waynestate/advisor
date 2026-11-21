<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Update;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Waynestate\Advisor\Command\DoctorCommand;
use Waynestate\Advisor\Command\McpCommand;
use Waynestate\Advisor\Tests\Support\TempDir;
use Waynestate\Advisor\Update\InstallKind;
use Waynestate\Advisor\Update\LatestReleaseFetcher;
use Waynestate\Advisor\Update\PackagistFetcher;
use Waynestate\Advisor\Update\UpdateCache;
use Waynestate\Advisor\Update\UpdateNotice;
use Waynestate\Advisor\Update\UpdateNoticeListener;

/**
 * The update notice: one stderr line when a newer stable release exists,
 * with the command that updates this install. Every test uses an injected
 * fetcher and clock and a temp cache dir, so nothing here touches the
 * network or the real cache.
 */
final class UpdateNoticeTest extends TestCase
{
    use TempDir;

    private const NOW = 1_763_000_000;

    /** The running version, as Advisor::VERSION. */
    private const RUNNING = '0.4.0';

    public function testLineForPhar(): void
    {
        // ADVISOR ships no PHAR, so a PHAR copy points at the Composer
        // global command, the only supported install path.
        $env = $this->env();

        [$notice, $fetcher] = $this->notice(InstallKind::Phar, [['version' => '0.5.0']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: composer global update waynestate/advisor\n",
            $sink['text'],
        );
        self::assertSame(1, $fetcher->calls);
    }

    public function testLineForSourceCheckout(): void
    {
        // A checkout with a .git of its own (folder or file) outside any
        // vendor/ folder is a source checkout.
        $root = $this->tempDir() . '/checkout';
        mkdir($root . '/bin', 0777, true);
        mkdir($root . '/src', 0777, true);
        file_put_contents($root . '/.git', "# worktree gitdir marker\n");
        $env = $this->env();

        self::assertSame(InstallKind::SourceCheckout, InstallKind::detect($root, $env));

        [$notice] = $this->notice(InstallKind::SourceCheckout, [['version' => '0.5.0']], self::NOW, $root);
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame("advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: git pull\n", $sink['text']);
    }

    public function testLineForComposerGlobal(): void
    {
        // <global home>/vendor/waynestate/advisor, with the global home
        // named by COMPOSER_HOME.
        $home = $this->tempDir() . '/composer-home';
        $root = $home . '/vendor/waynestate/advisor';
        mkdir($root . '/bin', 0777, true);
        $env = $this->env(['COMPOSER_HOME' => $home]);

        self::assertSame(InstallKind::ComposerGlobal, InstallKind::detect($root, $env));

        [$notice] = $this->notice(InstallKind::ComposerGlobal, [['version' => '0.5.0']], self::NOW, $root);
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: composer global update waynestate/advisor\n",
            $sink['text'],
        );
    }

    public function testLineForProjectDependency(): void
    {
        // <project>/vendor/waynestate/advisor, a project that is not
        // Composer's global home, pinning a constraint that still allows the
        // newest release.
        $project = $this->tempDir() . '/app';
        $root = $project . '/vendor/waynestate/advisor';
        mkdir($root . '/bin', 0777, true);
        file_put_contents($project . '/composer.json', (string) json_encode([
            'require' => ['waynestate/advisor' => '^0.4'],
        ], JSON_PRETTY_PRINT));
        $env = $this->env();

        self::assertSame(InstallKind::ProjectDependency, InstallKind::detect($root, $env));

        [$notice] = $this->notice(InstallKind::ProjectDependency, [['version' => '0.4.1']], self::NOW, $root);
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.4.1 is the newest release. Update: composer update waynestate/advisor\n",
            $sink['text'],
        );
    }

    public function testLineForPinnedProjectDependency(): void
    {
        // A ^ or ~ pin that excludes the newest release switches the line to
        // a require of the newest major.minor; --dev only when the pin sits
        // in require-dev.
        $project = $this->tempDir() . '/app';
        $root = $project . '/vendor/waynestate/advisor';
        mkdir($root . '/bin', 0777, true);
        file_put_contents($project . '/composer.json', (string) json_encode([
            'require' => ['waynestate/advisor' => '^0.4'],
        ], JSON_PRETTY_PRINT));
        $env = $this->env();

        [$notice] = $this->notice(InstallKind::ProjectDependency, [['version' => '0.5.0']], self::NOW, $root);
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: composer require waynestate/advisor:^0.5\n",
            $sink['text'],
        );

        // The same pin in require-dev adds --dev.
        file_put_contents($project . '/composer.json', (string) json_encode([
            'require-dev' => ['waynestate/advisor' => '~0.4'],
        ], JSON_PRETTY_PRINT));
        $env = $this->env();

        [$notice] = $this->notice(InstallKind::ProjectDependency, [['version' => '0.5.0']], self::NOW, $root);
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: composer require waynestate/advisor:^0.5 --dev\n",
            $sink['text'],
        );
    }

    public function testLineForUnknownInstall(): void
    {
        // No .git, not under any vendor/: unknown, which falls back to the
        // Composer global command.
        $root = $this->tempDir() . '/loose';
        mkdir($root . '/bin', 0777, true);
        $env = $this->env();

        self::assertSame(InstallKind::Unknown, InstallKind::detect($root, $env));

        [$notice] = $this->notice(InstallKind::Unknown, [['version' => '0.5.0']], self::NOW, $root);
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: composer global update waynestate/advisor\n",
            $sink['text'],
        );
    }

    public function testCachedResultMakesNoFetch(): void
    {
        // A cache checked within the last day is printed from, without a
        // fetch, even when the fetcher would answer differently.
        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.5.0');

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame("advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: git pull\n", $sink['text']);
        self::assertSame(0, $fetcher->calls);
    }

    public function testStaleCacheFetchesOnce(): void
    {
        // A cache older than a day fetches once and rewrites the file.
        $env = $this->env();
        $path = $this->seedCache($env, self::NOW - 86401, '0.4.0');

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, [['version' => '0.5.0']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame("advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: git pull\n", $sink['text']);
        self::assertSame(1, $fetcher->calls);
        self::assertSame(['checked_at' => self::NOW, 'latest' => '0.5.0'], (new UpdateCache('advisor'))->read($path));
    }

    public function testOfflineIsSilent(): void
    {
        // No cache and a failed fetch: nothing is printed, and the empty
        // result is stamped so the next command does not try again for a
        // day.
        $env = $this->env();
        $path = (new UpdateCache('advisor'))->path($env);

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, null, self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
        self::assertSame(1, $fetcher->calls);
        self::assertSame(['checked_at' => self::NOW, 'latest' => null], (new UpdateCache('advisor'))->read($path));
    }

    public function testMalformedJsonIsSilent(): void
    {
        // Malformed p2 bodies parse to null, and a null fetch stays silent
        // with the stamp refreshed.
        self::assertNull(PackagistFetcher::parse('not json'));
        self::assertNull(PackagistFetcher::parse('{"packages": {}}'));
        self::assertNull(PackagistFetcher::parse('{"packages": {"waynestate/advisor": "nope"}}'));
        self::assertSame(
            [['version' => '0.5.0']],
            PackagistFetcher::parse('{"packages": {"waynestate/advisor": [{"version": "0.5.0"}]}}'),
        );

        $env = $this->env();
        $path = (new UpdateCache('advisor'))->path($env);

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, null, self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
        self::assertSame(1, $fetcher->calls);
        self::assertSame(['checked_at' => self::NOW, 'latest' => null], (new UpdateCache('advisor'))->read($path));
    }

    public function testCiEnvIsSilent(): void
    {
        // CI set to anything other than empty, 0 or false stays silent
        // before the cache is even read.
        $env = $this->env(['CI' => '1']);
        $this->seedCache($env, self::NOW, '0.5.0');

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
        self::assertSame(0, $fetcher->calls);
    }

    public function testOptOutEnvIsSilent(): void
    {
        $env = $this->env(['WAYNE_NO_UPDATE_CHECK' => '1']);
        $this->seedCache($env, self::NOW, '0.5.0');

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
        self::assertSame(0, $fetcher->calls);
    }

    public function testQuietIsSilent(): void
    {
        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.5.0');

        [$notice, $fetcher] = $this->notice(InstallKind::SourceCheckout, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list', '--quiet'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
        self::assertSame(0, $fetcher->calls);

        // The short form is quiet too.
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list', '-q'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
    }

    public function testStdoutUntouchedByJsonFormat(): void
    {
        // ADVISOR has no --format=json (doctor has --json), so the
        // contract's requirement is that stdout is byte-identical with the
        // check on and off. This repo has no proc_open-based bin tests, so
        // the real TERMINATE listener is fired with the command's output as
        // a buffer holding doctor's JSON: the buffer must come out
        // unchanged, and the line must land on the stderr sink alone. The
        // cache is seeded and the fetcher would answer differently, so
        // nothing touches the network.
        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.4.1');

        [$notice, $fetcher] = $this->notice(InstallKind::ProjectDependency, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();
        $listener = $this->listener($notice, $write, ['advisor', 'doctor', '--json'], $env);

        $output = new BufferedOutput();
        $json = '{"findings": [], "ok": true}';
        $output->write($json);

        $listener(new ConsoleTerminateEvent(new ArrayInput([]), $output, 0, new DoctorCommand()));

        self::assertSame($json, $output->fetch(), 'stdout changed');
        self::assertSame(0, $fetcher->calls);
        self::assertSame(
            "advisor 0.4.0 is out of date: 0.4.1 is the newest release. Update: composer update waynestate/advisor\n",
            $sink['text'],
        );
    }

    public function testMcpServerIsSilent(): void
    {
        // The mcp command speaks the protocol on stdout and logs on stderr,
        // so the notice stays silent for it, even with a fresh newer
        // release in the cache. A one-shot command of the same application
        // still gets the line.
        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.4.1');

        [$notice, $fetcher] = $this->notice(InstallKind::ProjectDependency, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();
        $listener = $this->listener($notice, $write, ['advisor', 'mcp'], $env);

        $listener(new ConsoleTerminateEvent(new ArrayInput([]), new BufferedOutput(), 0, new McpCommand()));

        self::assertSame('', $sink['text']);
        self::assertSame(0, $fetcher->calls);

        [$write, $sink] = $this->stderr();

        $listener(new ConsoleTerminateEvent(new ArrayInput([]), new BufferedOutput(), 0, new DoctorCommand()));

        self::assertSame(
            "advisor 0.4.0 is out of date: 0.4.1 is the newest release. Update: composer update waynestate/advisor\n",
            $sink['text'],
        );
    }

    public function testQuietThroughEventOutputIsSilent(): void
    {
        // --quiet/-q also reach the listener through the event's output: a
        // quiet output silences the line even when argv carries no flag.
        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.4.1');

        [$notice, $fetcher] = $this->notice(InstallKind::ProjectDependency, [['version' => '9.9.9']], self::NOW, '');
        [$write, $sink] = $this->stderr();
        $listener = $this->listener($notice, $write, ['advisor', 'doctor'], $env);

        $quiet = new BufferedOutput(OutputInterface::VERBOSITY_QUIET);
        $listener(new ConsoleTerminateEvent(new ArrayInput([]), $quiet, 0, new DoctorCommand()));

        self::assertSame('', $sink['text']);
        self::assertSame(0, $fetcher->calls);
    }

    public function testNormalisesVPrefix(): void
    {
        // Tags may start with v or not: both normalise, and the line prints
        // the version without the v.
        $env = $this->env();
        $entries = [
            ['version' => 'v0.5.0'],
            ['version' => '0.4.9'],
            ['version' => 'v0.4.0'],
        ];

        [$notice] = $this->notice(InstallKind::SourceCheckout, $entries, self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame("advisor 0.4.0 is out of date: 0.5.0 is the newest release. Update: git pull\n", $sink['text']);
    }

    public function testIgnoresPrereleases(): void
    {
        // dev, alpha, beta and rc versions (any case) are dropped, so the
        // line names the highest stable release.
        $env = $this->env();
        $entries = [
            ['version' => '1.0.0-beta1'],
            ['version' => '1.0.0-RC1'],
            ['version' => 'dev-main'],
            ['version' => '0.5.0-alpha'],
            ['version' => '0.4.6-BETA'],
            ['version' => '0.4.5'],
        ];

        [$notice] = $this->notice(InstallKind::SourceCheckout, $entries, self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame("advisor 0.4.0 is out of date: 0.4.5 is the newest release. Update: git pull\n", $sink['text']);
    }

    public function testMajorBumpAddsUpgradingLink(): void
    {
        $env = $this->env();

        [$notice] = $this->notice(InstallKind::SourceCheckout, [['version' => '1.0.0']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame(
            "advisor 0.4.0 is out of date: 1.0.0 is the newest release. Update: git pull Upgrading: https://advisor.apps.wayne.edu/docs/upgrading/\n",
            $sink['text'],
        );

        // A minor bump stays on the same major and adds no link.
        $env = $this->env();

        [$notice] = $this->notice(InstallKind::SourceCheckout, [['version' => '0.9.0']], self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame("advisor 0.4.0 is out of date: 0.9.0 is the newest release. Update: git pull\n", $sink['text']);
    }

    public function testNotNewerIsSilent(): void
    {
        // A running version equal to, or newer than, the cached latest,
        // prints nothing.
        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.4.0');

        [$notice] = $this->notice(InstallKind::SourceCheckout, null, self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);

        $env = $this->env();
        $this->seedCache($env, self::NOW, '0.3.9');

        [$notice] = $this->notice(InstallKind::SourceCheckout, null, self::NOW, '');
        [$write, $sink] = $this->stderr();

        $notice->run($env, ['advisor', 'list'], self::RUNNING, $write);

        self::assertSame('', $sink['text']);
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function env(array $extra = []): array
    {
        return array_merge(['XDG_CACHE_HOME' => $this->cacheDir()], $extra);
    }

    private function cacheDir(): string
    {
        return $this->tempDir() . '/cache';
    }

    /**
     * @param array<string, string> $env
     */
    private function seedCache(array $env, int $checkedAt, ?string $latest): string
    {
        $cache = new UpdateCache('advisor');
        $path = $cache->path($env);
        self::assertTrue($cache->write($path, $checkedAt, $latest));

        return $path;
    }

    /**
     * @return array{0: callable(string): void, 1: array{text: string}}
     */
    private function stderr(): array
    {
        $sink = ['text' => ''];
        $write = static function (string $message) use (&$sink): void {
            $sink['text'] .= $message;
        };

        return [$write, $sink];
    }

    /**
     * @param list<array<string, mixed>>|null $entries
     * @return array{0: UpdateNotice, 1: FixedFetcher}
     */
    private function notice(InstallKind $kind, ?array $entries, int $now, string $packageRoot): array
    {
        $fetcher = new FixedFetcher($entries);
        $notice = new UpdateNotice(
            'advisor',
            $packageRoot,
            $kind,
            new UpdateCache('advisor'),
            $fetcher,
            static fn (): int => $now,
        );

        return [$notice, $fetcher];
    }

    /**
     * @param array<string, string> $env
     * @param list<string>          $argv
     */
    private function listener(UpdateNotice $notice, callable $stderr, array $argv, array $env): UpdateNoticeListener
    {
        return new UpdateNoticeListener($notice, self::RUNNING, $argv, $env, $stderr);
    }
}

/**
 * A LatestReleaseFetcher that counts its calls and answers with fixed
 * entries, so the tests need no network.
 */
final class FixedFetcher implements LatestReleaseFetcher
{
    public int $calls = 0;

    /**
     * @param list<array<string, mixed>>|null $entries
     */
    public function __construct(private readonly ?array $entries) {}

    public function fetch(): ?array
    {
        $this->calls++;

        return $this->entries;
    }
}
