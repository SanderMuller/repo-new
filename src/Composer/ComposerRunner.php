<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Composer;

use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Wraps `composer install` + `composer require` via symfony/process.
 *
 * On failure: surfaces stderr via ComposerFailureSurfacer and throws.
 * Caller decides whether to exit (NewCommand maps to exit code 65).
 */
final readonly class ComposerRunner implements ComposerRunnerInterface
{
    public function __construct(
        private SymfonyStyle $io,
        private ComposerFailureSurfacer $surfacer,
        private string $composerBinary = 'composer',
    ) {}

    public function install(string $cwd): void
    {
        $this->run(['install', '--no-interaction', '--no-scripts'], $cwd, 'composer install');
    }

    /**
     * @param  list<string>  $packages
     */
    public function require(string $cwd, array $packages, bool $dev = false): void
    {
        if ($packages === []) {
            return;
        }

        $args = ['require', '--no-interaction', '--no-scripts', '--with-all-dependencies'];
        if ($dev) {
            $args[] = '--dev';
        }

        foreach ($packages as $pkg) {
            // Entries shaped "name: ^x.y" → pass as "name:^x.y" (composer accepts it).
            $args[] = $this->normalizeConstraint($pkg);
        }

        $label = $dev ? 'composer require --dev' : 'composer require';
        $this->run($args, $cwd, $label);
    }

    /**
     * @param  list<string>  $packages
     */
    public function remove(string $cwd, array $packages, bool $noUpdate = false): void
    {
        if ($packages === []) {
            return;
        }

        $args = ['remove', '--no-interaction'];
        if ($noUpdate) {
            $args[] = '--no-update';
        }

        foreach ($packages as $pkg) {
            // For remove, just the name (no version constraint).
            $args[] = explode(':', $pkg, 2)[0];
        }

        $this->run($args, $cwd, 'composer remove');
    }

    /**
     * @param  list<string>  $args
     */
    private function run(array $args, string $cwd, string $label): void
    {
        $cmd = array_merge([$this->composerBinary], $args);

        $this->io->writeln("<info>→ {$label}</info>");

        $process = new Process($cmd, $cwd, null, null, 600.0);
        $process->run(function (string $type, string $buffer): void {
            $this->io->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->surfacer->surface($label, $process->getOutput(), $process->getErrorOutput(), $process->getExitCode() ?? 1);
            throw new RuntimeException("{$label} failed");
        }
    }

    private function normalizeConstraint(string $entry): string
    {
        // "foo/bar: ^1.0" → "foo/bar:^1.0"
        if (str_contains($entry, ':')) {
            [$name, $constraint] = array_map(trim(...), explode(':', $entry, 2));

            return "{$name}:{$constraint}";
        }

        return trim($entry);
    }
}
