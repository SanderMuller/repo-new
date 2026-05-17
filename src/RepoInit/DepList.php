<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\RepoInit;

/**
 * Value object for a resolved set of composer dependencies for a given category.
 *
 * `require` and `requireDev` are lists of constraint strings shaped exactly
 * like `composer require` arguments (e.g. `"illuminate/support: ^11.0"` or
 * the bare `"phpstan/phpstan"`).
 *
 * Constraints may contain `__LARAVEL_VERSIONS__` placeholder; substitute
 * before passing to ComposerRunner.
 */
final readonly class DepList
{
    /**
     * @param  list<string>  $require
     * @param  list<string>  $requireDev
     */
    public function __construct(
        public array $require,
        public array $requireDev,
    ) {}
}
