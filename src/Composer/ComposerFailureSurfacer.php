<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Composer;

use Symfony\Component\Console\Style\SymfonyStyle;

final class ComposerFailureSurfacer
{
    public function __construct(private readonly SymfonyStyle $io) {}

    public function surface(string $step, string $stdout, string $stderr, int $exitCode): void
    {
        $this->io->newLine();
        $this->io->error("[repo-new] {$step} failed (exit {$exitCode}).");

        if ($stdout !== '') {
            $this->io->writeln('<comment>stdout:</comment>');
            $this->io->writeln($stdout);
        }
        if ($stderr !== '') {
            $this->io->writeln('<comment>stderr:</comment>');
            $this->io->writeln($stderr);
        }

        $this->io->note('Partial state left for inspection. Fix manually + re-run individual composer commands, or rm -rf and try again.');
    }
}
