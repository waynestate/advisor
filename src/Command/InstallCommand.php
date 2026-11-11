<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the MCP client configuration that starts ADVISOR, or writes it with
 * --write. Printing is the default so nothing lands on disk by surprise.
 */
#[AsCommand(name: 'install', description: 'Print (or with --write, write) the MCP client config that starts ADVISOR.')]
final class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('client', 'c', InputOption::VALUE_REQUIRED, 'claude, warriorgpt, cursor, vscode or codex.', 'claude')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Write the file in the project instead of printing it.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Replace an existing, different "advisor" entry.')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Project folder to write into.', '.')
            ->addOption('runner', null, InputOption::VALUE_REQUIRED, 'How to start ADVISOR: docker (inside the app container) or php (on the host). Detected from the project when omitted.', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = strtolower((string) $input->getOption('client'));

        try {
            $runner = $this->runner($input);
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 2;
        }

        if ($client === 'codex') {
            if ($input->getOption('write')) {
                $output->writeln('<error>Codex uses TOML, which ADVISOR prints but does not merge. Add this to ~/.codex/config.toml yourself:</error>');
                $output->write(ClientConfig::codexToml($runner));

                return 2;
            }
            $output->write(ClientConfig::codexToml($runner));

            return 0;
        }

        try {
            if (!$input->getOption('write')) {
                $output->writeln('# ' . ClientConfig::file($client) . ' (run with --write to create it; review it like code, and do not commit it)');
                $output->write(ClientConfig::render($client, $runner));

                return 0;
            }

            $root = realpath((string) $input->getOption('path'));
            if ($root === false || !is_dir($root)) {
                throw new InvalidArgumentException('The project folder does not exist.');
            }
            $relative = ClientConfig::file($client);
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $existing = is_file($target) ? (string) file_get_contents($target) : '';
            $merged = ClientConfig::merge($existing, $client, (bool) $input->getOption('force'), $runner);

            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new InvalidArgumentException('Cannot create the folder for ' . $relative . '.');
            }
            file_put_contents($target, $merged);
            $output->writeln('Wrote ' . $relative . '. Approve the "advisor" server by name the first time your client asks.');
            if ($runner === 'docker') {
                $output->writeln('Runs inside the app container: start it with `parity up` or `docker compose up -d` first.');
            }
            if (!self::ignored($root, $relative)) {
                $output->writeln('<comment>' . $relative . ' is not in .gitignore. It starts a program on whoever opens the project, so either ignore it or review it like code.</comment>');
            }
        } catch (InvalidArgumentException|\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 2;
        }

        return 0;
    }

    /**
     * The runner the user asked for, or the one the project suggests: docker
     * when the folder holds a compose file or parity.json (Parity and Docker
     * projects have no PHP on the host), else php.
     */
    private function runner(InputInterface $input): string
    {
        $runner = (string) $input->getOption('runner');
        if ($runner !== '') {
            return match ($runner) {
                'docker', 'php' => $runner,
                default => throw new InvalidArgumentException('The runner must be docker or php.'),
            };
        }

        $dir = realpath((string) $input->getOption('path'));
        if ($dir === false || !is_dir($dir)) {
            return 'php';
        }
        foreach (['compose.yaml', 'compose.yml', 'docker-compose.yml', 'docker-compose.yaml', 'parity.json'] as $marker) {
            if (is_file($dir . DIRECTORY_SEPARATOR . $marker)) {
                return 'docker';
            }
        }

        return 'php';
    }

    private static function ignored(string $root, string $relative): bool
    {
        $ignore = $root . DIRECTORY_SEPARATOR . '.gitignore';
        if (!is_file($ignore)) {
            return false;
        }
        foreach (preg_split('/\R/', (string) file_get_contents($ignore)) ?: [] as $line) {
            $line = trim($line);
            if ($line === $relative || $line === '/' . $relative) {
                return true;
            }
        }

        return false;
    }
}
