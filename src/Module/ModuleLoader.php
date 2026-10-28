<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

use RuntimeException;
use Waynestate\Advisor\Support\Json;

/**
 * Loads modules from a manifest and from nowhere else (best practice 3.1).
 *
 * No folder is scanned. A class loads only if the manifest names it, it sits
 * in an allowed vendor namespace, and it implements Module. Anything else
 * stops the server at start-up with a message, instead of loading quietly.
 */
final class ModuleLoader
{
    /**
     * @param list<string> $allowedPrefixes namespace prefixes a module class may live in
     */
    public function __construct(
        private readonly array $allowedPrefixes = ['Waynestate\\'],
    ) {}

    /**
     * @param list<string> $manifestFiles the built-in manifest first, then any extra one
     *
     * @return list<Module>
     */
    public function load(array $manifestFiles, ModuleContext $context): array
    {
        $modules = [];
        $seen = [];
        foreach ($manifestFiles as $file) {
            foreach ($this->classesIn($file) as $class) {
                if (isset($seen[$class])) {
                    continue;
                }
                $seen[$class] = true;
                $modules[] = $this->instantiate($class, $context);
            }
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    public function classesIn(string $manifestFile): array
    {
        if (!is_file($manifestFile)) {
            throw new RuntimeException('The module manifest was not found.');
        }
        $data = Json::decodeFile($manifestFile);
        $classes = $data['modules'] ?? null;
        if (!is_array($classes) || array_filter($classes, static fn (mixed $c): bool => !is_string($c)) !== []) {
            throw new RuntimeException('The module manifest must hold a "modules" list of class names.');
        }

        /** @var list<string> $list */
        $list = array_values($classes);

        return $list;
    }

    private function instantiate(string $class, ModuleContext $context): Module
    {
        if (!$this->inAllowedNamespace($class)) {
            throw new RuntimeException("Module {$class} is outside the allowed vendor namespace and was not loaded.");
        }
        if (!class_exists($class)) {
            throw new RuntimeException("Module {$class} is listed in the manifest but is not installed.");
        }
        if (!is_subclass_of($class, Module::class)) {
            throw new RuntimeException("Module {$class} does not implement the Module interface.");
        }

        return new $class($context);
    }

    private function inAllowedNamespace(string $class): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $class)) {
            return false;
        }
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
