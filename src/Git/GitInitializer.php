<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Git;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class GitInitializer
{
    public function __construct(private readonly SymfonyStyle $io) {}

    public function init(string $targetDir): void
    {
        if (is_dir($targetDir . '/.git')) {
            return;
        }

        $process = new Process(['git', 'init', '--quiet'], $targetDir);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->io->warning('git init failed: ' . trim($process->getErrorOutput()));
        }
    }

    public function initialCommit(string $targetDir): void
    {
        $add = new Process(['git', 'add', '-A'], $targetDir);
        $add->run();

        if (! $add->isSuccessful()) {
            $this->io->warning('git add -A failed: ' . trim($add->getErrorOutput()));

            return;
        }

        $commit = new Process(
            ['git', 'commit', '-m', 'Initial scaffold via sandermuller/repo-new', '--quiet'],
            $targetDir,
        );
        $commit->run();

        if (! $commit->isSuccessful()) {
            $this->io->warning('git commit failed: ' . trim($commit->getErrorOutput()));
        }
    }
}
