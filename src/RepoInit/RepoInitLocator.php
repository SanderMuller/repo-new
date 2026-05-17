<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\RepoInit;

use RuntimeException;

/**
 * Locates the installed sandermuller/repo-init directory at runtime.
 *
 * Lookup order (per spec §4):
 *  1. Composer-global vendor: $COMPOSER_HOME/vendor/sandermuller/repo-init/
 *  2. Project-local: ./vendor/sandermuller/repo-init/
 *  3. Sibling-of-bin: __DIR__/../../../sandermuller/repo-init/
 */
final class RepoInitLocator
{
    public function __construct(private readonly ?string $cwd = null) {}

    public function locate(): string
    {
        foreach ($this->candidates() as $candidate) {
            if (is_dir($candidate) && is_file($candidate . '/references/per-category-deps.yml')) {
                $real = realpath($candidate);

                return rtrim($real !== false ? $real : $candidate, '/');
            }
        }

        throw new RuntimeException(
            'sandermuller/repo-init not found. Run `composer global require sandermuller/repo-init` first.',
        );
    }

    /** @return list<string> */
    private function candidates(): array
    {
        $candidates = [];

        $composerHome = $this->composerHome();
        if ($composerHome !== null) {
            $candidates[] = $composerHome . '/vendor/sandermuller/repo-init';
        }

        $cwd = $this->cwd ?? getcwd();
        if (is_string($cwd) && $cwd !== '') {
            $candidates[] = $cwd . '/vendor/sandermuller/repo-init';
        }

        // Sibling-of-bin: when this package is installed in a vendor dir,
        // repo-init is a sibling. __DIR__ here = .../src/RepoInit, so we go
        // up four to reach the vendor root, then into sandermuller/repo-init.
        $candidates[] = __DIR__ . '/../../../../sandermuller/repo-init';

        // Dev path: when cloned side-by-side (the dev composer.json uses a
        // `path` repository), the package may be symlinked from a sibling
        // directory. Try the parent of this package's root.
        $candidates[] = __DIR__ . '/../../../repo-init';

        return $candidates;
    }

    private function composerHome(): ?string
    {
        $env = getenv('COMPOSER_HOME');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $candidate = $home . '/.composer';
            if (is_dir($candidate)) {
                return $candidate;
            }

            $candidate = $home . '/.config/composer';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
