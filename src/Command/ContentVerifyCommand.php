<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waynestate\Advisor\Advisor;
use Waynestate\Advisor\Content\ContentBundle;

/**
 * Checks the bundled data/ against its manifest (hashes, no extra files, no
 * invisible characters). Needs no sibling repositories, so CI runs it.
 */
#[AsCommand(name: 'content:verify', description: 'Check the bundled content against its manifest.')]
final class ContentVerifyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $problems = (new ContentBundle(Advisor::dataDir()))->problems();
        if ($problems === []) {
            $output->writeln('data/ is sound.');

            return 0;
        }
        $output->writeln('<error>data/ failed its check:</error>');
        foreach ($problems as $problem) {
            $output->writeln('  ' . $problem);
        }

        return 1;
    }
}
