<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Git;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final readonly class GitInitializer
{
    public function __construct(private SymfonyStyle $io) {}

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

    /**
     * Whether the repository at $targetDir has a commit. Pest's Tia engine
     * stops the suite in a repository without one.
     */
    public function hasCommits(string $targetDir): bool
    {
        $process = new Process(['git', 'rev-parse', '--verify', '--quiet', 'HEAD'], $targetDir);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Whether Pest's Tia engine will stop the suite: the scaffold has
     * tests/Pest.php (where the Tia call lives) and the repository has no
     * commit. Scaffolds without Pest, such as skill-bundle, have no tests/Pest.php.
     */
    public function tiaNeedsCommit(string $targetDir): bool
    {
        return is_file($targetDir . '/tests/Pest.php') && ! $this->hasCommits($targetDir);
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
