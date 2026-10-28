<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Module;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waynestate\Advisor\Module\CoreModule;
use Waynestate\Advisor\Module\ModuleLoader;
use Waynestate\Advisor\Tests\Fixtures\FixtureModule;
use Waynestate\Advisor\Tests\Support\Boot;
use Waynestate\Advisor\Tests\Support\TempDir;

final class ModuleLoaderTest extends TestCase
{
    use TempDir;

    private function manifest(string ...$classes): string
    {
        $file = $this->tempDir() . '/modules.json';
        file_put_contents($file, (string) json_encode(['modules' => $classes]));

        return $file;
    }

    public function testLoadsTheClassesAManifestNames(): void
    {
        $context = Boot::core()->context;

        $modules = (new ModuleLoader())->load([$this->manifest(CoreModule::class, FixtureModule::class)], $context);

        $this->assertSame(['core', 'fixture'], array_map(static fn($m): string => $m->id(), $modules));
    }

    public function testTheShippedManifestLoadsTheCoreModule(): void
    {
        $context = Boot::core()->context;

        $modules = (new ModuleLoader())->load([dirname(__DIR__, 2) . '/modules.json'], $context);

        $this->assertCount(1, $modules);
        $this->assertInstanceOf(CoreModule::class, $modules[0]);
    }

    public function testASecondManifestAddsModulesWithoutDuplicates(): void
    {
        $context = Boot::core()->context;

        $modules = (new ModuleLoader())->load([$this->manifest(CoreModule::class), $this->manifest(CoreModule::class, FixtureModule::class)], $context);

        $this->assertCount(2, $modules);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refused(): array
    {
        return [
            'outside the vendor namespace' => ['Evil\\Backdoor\\Module', 'outside the allowed vendor namespace'],
            'a lookalike prefix' => ['WaynestateEvil\\Module', 'outside the allowed vendor namespace'],
            'a path instead of a class' => ['../../evil.php', 'outside the allowed vendor namespace'],
            'a class that is not installed' => ['Waynestate\\Proctor\\AdvisorModule', 'not installed'],
            'a class that is not a module' => ['Waynestate\\Advisor\\Tests\\Fixtures\\NotAModule', 'does not implement'],
        ];
    }

    #[DataProvider('refused')]
    public function testRefusesAnythingElseAndSaysWhy(string $class, string $message): void
    {
        $context = Boot::core()->context;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new ModuleLoader())->load([$this->manifest($class)], $context);
    }

    public function testClassesAreNotFoundByScanningAFolder(): void
    {
        // FixtureModule exists and autoloads, but nothing loads it unless a manifest names it.
        $context = Boot::core()->context;

        $modules = (new ModuleLoader())->load([dirname(__DIR__, 2) . '/modules.json'], $context);

        foreach ($modules as $module) {
            $this->assertNotInstanceOf(FixtureModule::class, $module);
        }
    }

    public function testABrokenManifestIsAnError(): void
    {
        $file = $this->tempDir() . '/modules.json';
        file_put_contents($file, '{"modules":"CoreModule"}');
        $context = Boot::core()->context;

        $this->expectException(RuntimeException::class);
        (new ModuleLoader())->load([$file], $context);
    }

    public function testAMissingManifestIsAnError(): void
    {
        $context = Boot::core()->context;

        $this->expectException(RuntimeException::class);
        (new ModuleLoader())->load([$this->tempDir() . '/none.json'], $context);
    }
}
