<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Asks which `sandermuller/boost-skills` capability tags to write into the
 * scaffolded `boost.php` `->withTags(...)`.
 *
 * Package categories only — `laravel-project` uses `laravel/boost` and
 * scaffolds no `boost.php`, so it has no `withTags()` to fill.
 */
final class SkillTagsQuestion
{
    /** Tags offered by sandermuller/boost-skills. */
    public const array TAGS = ['php', 'frontend', 'github', 'jira'];

    /**
     * Pre-selected tags for package categories. A PHP package without `php`
     * misses boost-skills' backend-quality / pre-release set, so every package
     * category defaults to `php` (per repo-init's skill spec); the user adjusts
     * it interactively. `frontend` / `github` / `jira` are opt-in.
     *
     * @var list<string>
     */
    private const array DEFAULT_TAGS = ['php'];

    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category === 'laravel-project') {
            return;
        }

        // Already answered — via --skill-tags, or a prior call.
        if ($state->skillTags !== null) {
            return;
        }

        if (! $state->interactive) {
            $state->skillTags = self::DEFAULT_TAGS;

            return;
        }

        // Multi-select checklist with `php` pre-selected; the user confirms or
        // adjusts. choice() returns selected TAGS values (all strings) —
        // is_string keeps the list typed without an unsafe mixed→string cast.
        $chosen = $io->choice(
            'boost-skills tags for boost.php — adjust the checklist as needed',
            self::TAGS,
            implode(',', self::DEFAULT_TAGS),
            multiSelect: true,
        );
        $state->skillTags = array_values(array_filter((array) $chosen, is_string(...)));
    }
}
