<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\RepoInit;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses references/per-category-deps.yml and builds DepList for a category.
 *
 * Honors:
 *  - `shared.always.require-dev` (added to every category)
 *  - `shared.test-framework.pest|phpunit` (test-framework specific)
 *  - `shared.test-framework.pest-laravel-only` (added on top of pest for
 *    laravel-{project,package})
 *  - per-category `mandatory.require` and `mandatory.require-dev`
 *  - per-category `optional` opt-ins (selected via $optInFlags)
 *  - per-category `shared-exclusions` (dropped from shared lists)
 *  - per-opt-in `replaces-in-require-dev` (e.g. larastan replaces phpstan)
 */
final readonly class PerCategoryDeps
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(string $yamlPath)
    {
        if (! is_file($yamlPath)) {
            throw new RuntimeException("per-category-deps.yml not found at {$yamlPath}");
        }

        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile($yamlPath);
        $this->data = $data;
    }

    /**
     * @param  array<string, bool>  $optInFlags  Map of opt-in name → enabled
     */
    public function forCategory(
        string $category,
        string $testFramework,
        array $optInFlags = [],
    ): DepList {
        /** @var array<string, array<string, mixed>> $categories */
        $categories = $this->data['categories'] ?? [];
        if (! isset($categories[$category])) {
            throw new RuntimeException("Unknown category: {$category}");
        }

        $cat = $categories[$category];

        /** @var list<string> $require */
        $require = $this->listFrom($cat, ['mandatory', 'require']);
        /** @var list<string> $requireDev */
        $requireDev = $this->listFrom($cat, ['mandatory', 'require-dev']);

        // `consumes-shared-dev-deps: false` (skill-bundle) means the category's
        // dev deps come solely from its own `mandatory` block — skip BOTH the
        // shared.always list AND the shared.test-framework block. Absent ⇒ true.
        $consumesSharedDevDeps = $cat['consumes-shared-dev-deps'] ?? true;

        if ($consumesSharedDevDeps !== false) {
            // Shared always.
            /** @var list<string> $sharedAlwaysDev */
            $sharedAlwaysDev = $this->listFrom($this->data, ['shared', 'always', 'require-dev']);

            // Test-framework deps.
            /** @var list<string> $testDev */
            $testDev = $this->listFrom($this->data, ['shared', 'test-framework', $testFramework, 'require-dev']);

            // Pest laravel-only addition.
            if ($testFramework === 'pest' && in_array($category, ['laravel-project', 'laravel-package'], true)) {
                /** @var list<string> $pestLaravelOnly */
                $pestLaravelOnly = $this->listFrom($this->data, ['shared', 'test-framework', 'pest-laravel-only', 'require-dev']);
                $testDev = array_values(array_merge($testDev, $pestLaravelOnly));
            }

            // Compose shared dev list.
            $sharedDev = array_values(array_merge($sharedAlwaysDev, $testDev));

            // Apply per-category shared-exclusions to the shared list.
            /** @var list<string> $exclusions */
            $exclusions = $cat['shared-exclusions'] ?? [];
            $sharedDev = $this->dropByPackage($sharedDev, $exclusions);

            $requireDev = array_values(array_merge($requireDev, $sharedDev));
        }

        // Apply opt-ins.
        /** @var array<string, array<string, mixed>> $optional */
        $optional = $cat['optional'] ?? [];
        foreach ($optInFlags as $optInName => $enabled) {
            if (! $enabled) {
                continue;
            }

            if (! isset($optional[$optInName])) {
                continue;
            }

            $opt = $optional[$optInName];
            /** @var list<string> $optReq */
            $optReq = $opt['require'] ?? [];
            /** @var list<string> $optDev */
            $optDev = $opt['require-dev'] ?? [];

            // replaces-in-require-dev: drop those before adding new ones.
            /** @var list<string> $replaces */
            $replaces = $opt['replaces-in-require-dev'] ?? [];
            if ($replaces !== []) {
                $requireDev = $this->dropByPackage($requireDev, $replaces);
            }

            $require = array_values(array_merge($require, $optReq));
            $requireDev = array_values(array_merge($requireDev, $optDev));
        }

        // Dedupe (last write wins keeping original order).
        $require = $this->dedupeByPackage($require);
        $requireDev = $this->dedupeByPackage($requireDev);

        return new DepList($require, $requireDev);
    }

    /**
     * Stub directory for a category. `laravel-package` always scaffolds the
     * spatie/laravel-package-tools-based stub (`laravel-package-spatie`);
     * every other category maps 1:1 to its own name.
     */
    public function stubDirFor(string $category): string
    {
        if ($category === 'laravel-package') {
            return 'laravel-package-spatie';
        }

        return $category;
    }

    /**
     * Stub-relative paths under stubs/shared/ that the category does NOT
     * plain-copy — the `shared-stub-skip` denylist in per-category-deps.yml.
     * Absent ⇒ empty list (the category copies all of stubs/shared/).
     *
     * @return list<string>
     */
    public function sharedStubSkipFor(string $category): array
    {
        return $this->listFrom($this->data, ['categories', $category, 'shared-stub-skip']);
    }

    /**
     * @param  array<int|string, mixed>  $haystack
     * @param  list<int|string>  $path
     * @return list<string>
     */
    private function listFrom(array $haystack, array $path): array
    {
        $cursor = $haystack;
        foreach ($path as $key) {
            if (! is_array($cursor) || ! array_key_exists($key, $cursor)) {
                return [];
            }

            $cursor = $cursor[$key];
        }

        if (! is_array($cursor)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): string => (string) $v, $cursor));
    }

    /**
     * @param  list<string>  $list
     * @param  list<string>  $packagesToDrop
     * @return list<string>
     */
    private function dropByPackage(array $list, array $packagesToDrop): array
    {
        $dropSet = array_flip(array_map($this->packageName(...), $packagesToDrop));

        return array_values(array_filter(
            $list,
            fn (string $entry): bool => ! isset($dropSet[$this->packageName($entry)]),
        ));
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    private function dedupeByPackage(array $list): array
    {
        $seen = [];
        $out = [];
        foreach ($list as $entry) {
            $name = $this->packageName($entry);
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Extract package name from an entry like `"foo/bar: ^1.0"` or `"foo/bar"`.
     */
    private function packageName(string $entry): string
    {
        if (str_contains($entry, ':')) {
            return trim(explode(':', $entry, 2)[0]);
        }

        return trim($entry);
    }
}
