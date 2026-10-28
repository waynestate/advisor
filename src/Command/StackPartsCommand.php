<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waynestate\Advisor\Content\PartsTable;
use Waynestate\Advisor\Support\Json;

/**
 * Renders the stack parts table from the project seed and keeps the marked
 * block in a README in step with it.
 *
 * With no options it prints the table. --write=<readme> replaces the block
 * between the stack-parts markers; --check=<readme> exits 1 when the block is
 * stale. A file without both markers is an error (exit 2) and is never edited.
 */
#[AsCommand(name: 'stack-parts', description: 'Render the stack parts table from the seed; write or check the marked block in a README.')]
final class StackPartsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('write', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Replace the block between the stack-parts markers in this file (repeatable)')
            ->addOption('check', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exit 1 when the block in this file is stale (repeatable)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $projects = self::seed();
        } catch (RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 2;
        }

        $write = (array) $input->getOption('write');
        $check = (array) $input->getOption('check');

        if ($write === [] && $check === []) {
            $output->write(PartsTable::render($projects));

            return 0;
        }

        foreach ($write as $path) {
            try {
                PartsTable::write($path, $projects);
            } catch (RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');

                return 2;
            }
            $output->writeln('Updated the stack-parts block in ' . $path . '.');
        }

        /** @var list<string> $check */
        $stale = PartsTable::check($check, $projects);
        if ($stale !== []) {
            $output->writeln('<error>The stack-parts block is stale:</error>');
            foreach ($stale as $line) {
                $output->writeln('  ' . $line);
            }

            return 1;
        }
        $output->writeln('The stack-parts block is up to date in ' . count($check) . ' file' . (count($check) === 1 ? '' : 's') . '.');

        return 0;
    }

    /**
     * The seed projects, in seed order. Every project must carry a name and a
     * tagline; the table is only as good as the seed.
     *
     * @return list<array{name: string, tagline: string}>
     */
    private static function seed(): array
    {
        $seed = Json::decodeFile(dirname(__DIR__, 2) . '/generator/projects.seed.json');
        $projects = $seed['projects'] ?? [];
        if ($projects === []) {
            throw new RuntimeException('stack-parts: the seed has no projects.');
        }

        $out = [];
        foreach ($projects as $project) {
            $name = $project['name'] ?? null;
            $tagline = $project['tagline'] ?? null;
            if (!is_string($name) || $name === '' || !is_string($tagline) || $tagline === '') {
                throw new RuntimeException('stack-parts: ' . ($project['id'] ?? '?') . ' is missing a name or a tagline in the seed.');
            }
            $out[] = ['name' => $name, 'tagline' => $tagline];
        }

        return $out;
    }
}
