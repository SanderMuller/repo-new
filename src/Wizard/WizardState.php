<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard;

/**
 * Holds wizard answers + derived defaults for one scaffold run.
 *
 * Mutable bag — questions populate fields one at a time. Treat as
 * write-once-per-field (no question should overwrite another's answer).
 */
final class WizardState
{
    /** One of: laravel-project, laravel-package, php-package, phpstan-extension, rector-extension, composer-plugin, skill-bundle. */
    public ?string $category = null;

    /** Composer vendor, lowercase. Part before `/`. */
    public ?string $vendor = null;

    /** Composer package, kebab-case. Part after `/`. */
    public ?string $package = null;

    /** Free text. */
    public ?string $description = null;

    /** One of: 8.3, 8.4, 8.5. */
    public ?string $phpVersion = null;

    /** Constraint string. Defaults to ^11.0||^12.0||^13.0 for laravel-package. */
    public ?string $laravelVersions = null;

    /** Pest or phpunit. Vendor-derived default; user can override. */
    public ?string $testFramework = null;

    /** laravel-project only. */
    public bool $withHihahoRules = false;

    /** laravel-project only. */
    public bool $withSecurityAdvisories = false;

    /** phpstan-extension + rector-extension only. */
    public bool $laravelAware = false;

    /** composer-plugin only. One of: command-provider, event-subscriber, both, none. */
    public ?string $pluginShape = null;

    /** Resolved absolute path the scaffold writes into. */
    public ?string $targetDir = null;

    /** Whether to make an initial commit after scaffold. */
    public bool $commit = false;

    /** Whether to run interactively. */
    public bool $interactive = true;

    /** Author name (from git config or wizard). */
    public ?string $authorName = null;

    /** Author email (from git config or wizard). */
    public ?string $authorEmail = null;

    /**
     * Composer name (vendor/package), if both halves are set.
     */
    public function composerName(): ?string
    {
        if ($this->vendor === null || $this->package === null) {
            return null;
        }

        return "{$this->vendor}/{$this->package}";
    }

    /**
     * Apply vendor-driven defaults. Idempotent — only sets fields
     * that are still null/false.
     */
    public function applyDefaults(): void
    {
        if ($this->testFramework === null) {
            $this->testFramework = $this->defaultTestFramework();
        }

        if ($this->category === 'laravel-project' && $this->vendor === 'hihaho') {
            // No-op flag; opt-in only flipped on explicitly.
        }

        if ($this->category === 'laravel-package' && $this->laravelVersions === null) {
            $this->laravelVersions = '^11.0||^12.0||^13.0';
        }

        // phpstan/rector extension with --laravel-aware needs a Laravel
        // constraint for illuminate/* in require (per per-category-deps.yml).
        if ($this->laravelAware && $this->laravelVersions === null
            && in_array($this->category, ['phpstan-extension', 'rector-extension'], true)) {
            $this->laravelVersions = '^11.0||^12.0||^13.0';
        }

        if ($this->phpVersion === null) {
            $this->phpVersion = '8.3';
        }
    }

    private function defaultTestFramework(): string
    {
        // phpstan-extension + rector-extension always phpunit (per spec §3
        // vendor-driven defaults; extension stubs hardcode phpunit scripts;
        // PHPStan's RuleTestCase is PHPUnit-based).
        if (in_array($this->category, ['phpstan-extension', 'rector-extension'], true)) {
            return 'phpunit';
        }

        // laravel-project ships PHPUnit via `laravel new` by default.
        // pestphp/pest-plugin-laravel lags Laravel versions (Laravel 13
        // requires pest-plugin-laravel ^4.1; older Laravel can use earlier).
        // Default to phpunit; users opt into pest via --test-framework=pest
        // (then they must run `vendor/bin/pest --init` separately to migrate
        // tests). Sander-vendor convention also defaults laravel-project to
        // phpunit since pest-on-Laravel-13 is fragile.
        if ($this->category === 'laravel-project') {
            return match ($this->vendor) {
                'sandermuller' => 'phpunit',  // override the sander → pest default for laravel-project
                default => 'phpunit',
            };
        }

        return match ($this->vendor) {
            'hihaho' => 'phpunit',
            default => 'pest',
        };
    }
}
