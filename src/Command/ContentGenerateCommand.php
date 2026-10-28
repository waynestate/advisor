<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Content\Generator;
use Waynestate\Advisor\Support\SystemClock;

/**
 * Rebuilds data/ from the sibling stack repositories, or with --check says
 * whether data/ is out of date. It reads those repositories and never writes
 * to them.
 */
#[AsCommand(name: 'content:generate', description: 'Rebuild the bundled content from the stack repositories (--check only compares).')]
final class ContentGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('sources', 's', InputOption::VALUE_REQUIRED, 'Folder that holds the stack repositories (creed, proctor, wayne-ui, ...). Default: ADVISOR_STACK_DIR.')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Exit 1 when the bundle differs from what the sources produce; write nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sources = (string) ($input->getOption('sources') ?: getenv('ADVISOR_STACK_DIR'));
        if ($sources === '' || !is_dir($sources)) {
            $output->writeln('<error>Give --sources=<folder with the stack repositories> or set ADVISOR_STACK_DIR.</error>');

            return 2;
        }

        $generator = new Generator(
            rtrim($sources, '/\\'),
            Advisor::dataDir(),
            dirname(__DIR__, 2) . '/generator/projects.seed.json',
            new SystemClock(),
        );

        try {
            if ($input->getOption('check')) {
                $diff = $generator->check();
                if ($diff === []) {
                    $output->writeln('data/ matches the sources.');

                    return 0;
                }
                $output->writeln('<error>data/ is out of date. Run `composer content:generate` and review the diff:</error>');
                foreach ($diff as $line) {
                    $output->writeln('  ' . $line);
                }

                return 1;
            }

            $diff = $generator->generate();
            $output->writeln($diff === [] ? 'data/ was already up to date.' : 'Updated data/ (' . count($diff) . ' files changed). Review the diff before committing.');
        } catch (RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 2;
        }

        return 0;
    }
}
