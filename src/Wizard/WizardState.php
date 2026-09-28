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
    public const string DEFAULT_LARAVEL_VERSIONS = '^12.0||^13.0';

    /** One of: laravel-project, laravel-package, php-package, phpstan-extension, rector-extension, composer-plugin, skill-bundle. */
    public ?string $category = null;

    /** Composer vendor, lowercase. Part before `/`. */
    public ?string $vendor = null;

    /** Composer package, kebab-case. Part after `/`. */
    public ?string $package = null;

    /** Free text. */
    public ?string $description = null;

    /** One of PhpVersionPolicy::allowedFor($category). */
    public ?string $phpVersion = null;

    /** Constraint string. Defaults to DEFAULT_LARAVEL_VERSIONS for laravel-package and laravel-aware extensions. */
    public ?string $laravelVersions = null;

    /** Pest or phpunit. Category- and vendor-derived default; user can override. */
    public ?string $testFramework = null;

    /** laravel-project only. Null until a flag answers it; defaults to vendor === hihaho. */
    public ?bool $withHihahoRules = null;

    /** laravel-project only. */
    public bool $withHealthChecks = false;

    /**
     * laravel-package only. `sander` (plain ServiceProvider) or `spatie`
     * (spatie/laravel-package-tools). Defaults to `spatie` for vendor hihaho.
     */
    public ?string $variant = null;

    /** phpstan-extension + rector-extension only. */
    public bool $laravelAware = false;

    /** composer-plugin only. One of: command-provider, event-subscriber, both, none. */
    public ?string $pluginShape = null;

    /**
     * boost-skills capability tags written into .config/boost.php's withTags().
     * Package categories only; null until the wizard or --skill-tags answers it.
     *
     * @var list<string>|null
     */
    public ?array $skillTags = null;

    /** One of LicenseApplier::LICENSES. Defaults to proprietary for laravel-project, MIT otherwise. */
    public ?string $license = null;

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
     * Apply category- and vendor-driven defaults. Idempotent — only sets
     * fields that are still null.
     */
    public function applyDefaults(): void
    {
        $this->testFramework ??= $this->defaultTestFramework();

        $this->withHihahoRules ??= $this->category === 'laravel-project' && $this->vendor === 'hihaho';

        if ($this->category === 'laravel-package' && $this->variant === null) {
            $this->variant = $this->vendor === 'hihaho' ? 'spatie' : 'sander';
        }

        if ($this->category === 'laravel-package' && $this->laravelVersions === null) {
            $this->laravelVersions = self::DEFAULT_LARAVEL_VERSIONS;
        }

        // phpstan/rector extension with --laravel-aware needs a Laravel
        // constraint for illuminate/* in require (per per-category-deps.yml).
        if ($this->laravelAware && $this->laravelVersions === null
            && in_array($this->category, ['phpstan-extension', 'rector-extension'], true)) {
            $this->laravelVersions = self::DEFAULT_LARAVEL_VERSIONS;
        }

        $this->phpVersion ??= PhpVersionPolicy::defaultFor($this->category);

        $this->license ??= $this->category === 'laravel-project' ? 'proprietary' : 'MIT';
    }

    private function defaultTestFramework(): string
    {
        // phpstan-extension defaults to phpunit (PHPStan's RuleTestCase is
        // PHPUnit-based; bootstrap-phpstan-extension.md). laravel-project ships
        // PHPUnit via `laravel new`; pest there needs `pest --init`, which the
        // scaffolder does not run.
        if (in_array($this->category, ['phpstan-extension', 'laravel-project'], true)) {
            return 'phpunit';
        }

        return match ($this->vendor) {
            'hihaho' => 'phpunit',
            default => 'pest',
        };
    }
}
