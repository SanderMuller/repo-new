<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerJson;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;

/**
 * Composes the test-framework variant on top of the copied category stubs,
 * per the repo-init bootstrap phases ("Compose test-framework variant"):
 * each stub ships one framework (Pest or PHPUnit); this normalises the test
 * deps, scripts, allow-plugins entry, the CI test command and the rector.php
 * `withComposerBased(phpunit: true)` call to the chosen framework.
 *
 * Runs BEFORE `composer install`, so the first resolution already sees the
 * final dep set (a PHPUnit-pinned stub cannot take Pest 5 afterwards).
 */
final readonly class TestFrameworkSwapper
{
    /** version-defaults.md "PHPUnit" floor. */
    private const string PHPUNIT_CONSTRAINT = '^11.0||^12.0';

    /** Pest 5 cannot install next to testbench 10 (symfony/process ^8.1 vs ^7.2). */
    private const string TESTBENCH_PEST = '^11.0';

    private const string TESTBENCH_PHPUNIT = '^10.0||^11.0';

    private const array COMMANDS = [
        'pest' => ['test' => 'vendor/bin/pest', 'test-coverage' => 'vendor/bin/pest --coverage', 'ci' => 'vendor/bin/pest --ci'],
        'phpunit' => ['test' => 'vendor/bin/phpunit', 'test-coverage' => 'vendor/bin/phpunit --coverage-html=coverage', 'ci' => 'vendor/bin/phpunit'],
    ];

    public function __construct(private PerCategoryDeps $deps) {}

    public function apply(string $targetDir, string $category, string $framework): void
    {
        if (! isset(self::COMMANDS[$framework])) {
            throw new RuntimeException("Unknown test framework: {$framework}");
        }

        $this->swapComposerJson($targetDir . '/composer.json', $category, $framework);
        $this->setCiTestCommand($targetDir . '/.github/workflows/run-tests.yml', $framework);
        $this->setRectorComposerBased($targetDir . '/rector.php', $framework);
    }

    private function swapComposerJson(string $path, string $category, string $framework): void
    {
        $json = ComposerJson::read($path);
        $requireDev = ComposerJson::map($json, 'require-dev');

        if (! isset($requireDev['pestphp/pest']) && ! isset($requireDev['phpunit/phpunit'])) {
            return;
        }

        $json['require-dev'] = $this->swapTestDeps($requireDev, $category, $framework);

        $config = ComposerJson::map($json, 'config');
        $config['allow-plugins'] = $this->swapPestPluginAllowance(ComposerJson::map($config, 'allow-plugins'), $framework);
        $json['config'] = $config;

        $scripts = ComposerJson::map($json, 'scripts');
        foreach (['test', 'test-coverage'] as $script) {
            if (isset($scripts[$script])) {
                $scripts[$script] = self::COMMANDS[$framework][$script];
            }
        }

        if ($scripts !== []) {
            $json['scripts'] = $scripts;
        }

        ComposerJson::write($path, $json);
    }

    /**
     * @param  array<mixed>  $requireDev
     * @return array<mixed>
     */
    private function swapTestDeps(array $requireDev, string $category, string $framework): array
    {
        foreach (array_keys($requireDev) as $package) {
            $package = (string) $package;
            $isOtherFramework = $framework === 'pest'
                ? $package === 'phpunit/phpunit'
                : str_starts_with($package, 'pestphp/');

            if ($isOtherFramework) {
                unset($requireDev[$package]);
            }
        }

        foreach ($this->deps->testFrameworkDevDeps($category, $framework) as $entry) {
            $package = $this->deps->packageName($entry);
            $constraint = match (true) {
                str_contains($entry, ':') => trim(explode(':', $entry, 2)[1]),
                $package === 'phpunit/phpunit' => self::PHPUNIT_CONSTRAINT,
                default => '*',
            };
            $requireDev[$package] ??= $constraint;
        }

        if (isset($requireDev['orchestra/testbench'])) {
            $requireDev['orchestra/testbench'] = $framework === 'pest' ? self::TESTBENCH_PEST : self::TESTBENCH_PHPUNIT;
        }

        ksort($requireDev);

        return $requireDev;
    }

    /**
     * @param  array<mixed>  $allowPlugins
     * @return array<mixed>
     */
    private function swapPestPluginAllowance(array $allowPlugins, string $framework): array
    {
        if ($framework !== 'pest') {
            unset($allowPlugins['pestphp/pest-plugin']);

            return $allowPlugins;
        }

        $allowPlugins['pestphp/pest-plugin'] ??= true;
        ksort($allowPlugins);

        return $allowPlugins;
    }

    /**
     * Always normalised, not only on a swap: a stub may ship the other framework's command.
     */
    private function setCiTestCommand(string $path, string $framework): void
    {
        $this->rewrite($path, static fn (string $yaml): string => (string) preg_replace(
            '#^([ \t]*run:[ \t]*)vendor/bin/(?:pest --ci|phpunit)[ \t]*$#m',
            '${1}' . self::COMMANDS[$framework]['ci'],
            $yaml,
        ));
    }

    /**
     * PHPUnit: the config carries `->withComposerBased(phpunit: true)` right
     * after the `withPreparedSets(...)` block. Pest: no such call — the PHPUnit
     * composer-based set rewrites TestCase subclasses, which Pest has none of.
     */
    private function setRectorComposerBased(string $path, string $framework): void
    {
        $this->rewrite($path, static function (string $php) use ($framework): string {
            if ($framework === 'pest') {
                return (string) preg_replace('#^[ \t]*->withComposerBased\(phpunit: true\)\R#m', '', $php);
            }

            if (str_contains($php, '->withComposerBased(')) {
                return $php;
            }

            return (string) preg_replace(
                '#(->withPreparedSets\(.*?\R([ \t]*)\)\R)#s',
                '${1}${2}->withComposerBased(phpunit: true)' . "\n",
                $php,
                1,
            );
        });
    }

    /**
     * @param  callable(string): string  $transform
     */
    private function rewrite(string $path, callable $transform): void
    {
        if (! is_file($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read {$path}");
        }

        $updated = $transform($contents);
        if ($updated !== $contents && file_put_contents($path, $updated) === false) {
            throw new RuntimeException("Failed to write {$path}");
        }
    }
}
