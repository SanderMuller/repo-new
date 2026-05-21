<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

/**
 * Decides whether a `stubs/shared/` file is skipped for a category, per the
 * `shared-stub-skip` denylist from repo-init's per-category-deps.yml.
 *
 * Entries are stub-relative paths, matched against the raw (pre-rename) stub
 * path: an exact match, or — when the entry ends with `/` — a directory prefix.
 */
final readonly class SharedStubSkipper
{
    /** @param  list<string>  $skip */
    public function __construct(private array $skip) {}

    public function shouldSkip(string $relative): bool
    {
        foreach ($this->skip as $entry) {
            if (str_ends_with($entry, '/')) {
                if (str_starts_with($relative, $entry)) {
                    return true;
                }
            } elseif ($relative === $entry) {
                return true;
            }
        }

        return false;
    }
}
