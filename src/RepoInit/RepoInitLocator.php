<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\RepoInit;

use RuntimeException;

/**
 * Locates the installed sandermuller/repo-init directory at runtime.
 *
 * repo-new is tightly coupled to repo-init's stub + per-category-deps.yml
 * format, so it must use the repo-init version its own composer.json pinned —
 * never an unrelated ambient global that may be a different version. Lookup
 * order therefore puts repo-new's own resolved dependency (deterministic,
 * __DIR__-relative) first; ambient/global paths are fallbacks only:
 *  1. Vendor sibling: __DIR__/../../../../sandermuller/repo-init/
 *     (repo-new installed in a vendor/ dir — repo-init is a sibling).
 *  2. Project vendor: __DIR__/../../vendor/sandermuller/repo-init/
 *     (repo-new checked out as its own project — repo-init under vendor/).
 *  3. Composer-global vendor: $COMPOSER_HOME/vendor/sandermuller/repo-init/.
 *  4. Project-local: <cwd>/vendor/sandermuller/repo-init/.
 *  5. Side-by-side dev clone: __DIR__/../../../repo-init/.
 */
final readonly class RepoInitLocator
{
    public function __construct(private ?string $cwd = null) {}

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

        // repo-new's own repo-init dependency — deterministic, __DIR__-relative,
        // so it wins over any ambient/global copy. repo-new must use the
        // repo-init version its composer.json pinned. __DIR__ = .../src/RepoInit.
        // (1) repo-new installed in a vendor dir: repo-init is a vendor sibling
        //     (up four to the vendor root, then into sandermuller/repo-init).
        $candidates[] = __DIR__ . '/../../../../sandermuller/repo-init';
        // (2) repo-new checked out as its own project: repo-init under vendor/.
        $candidates[] = __DIR__ . '/../../vendor/sandermuller/repo-init';

        // Ambient fallbacks — only reached when repo-new's own dependency is
        // not where expected (unusual installs).
        $composerHome = $this->composerHome();
        if ($composerHome !== null) {
            $candidates[] = $composerHome . '/vendor/sandermuller/repo-init';
        }

        $cwd = $this->cwd ?? getcwd();
        if (is_string($cwd) && $cwd !== '') {
            $candidates[] = $cwd . '/vendor/sandermuller/repo-init';
        }

        // Side-by-side dev clone: when cloned next to repo-new (the dev
        // composer.json uses a `path` repository), repo-init may be a sibling
        // directory of the package root.
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
