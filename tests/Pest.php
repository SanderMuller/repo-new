<?php declare(strict_types=1);
use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is
| `PHPUnit\Framework\TestCase`. For Laravel-aware packages, the bootstrap
| phase replaces this with `Orchestra\Testbench\TestCase` for Feature
| tests, or a project-specific test case.
|
*/

// pest()->extend(Orchestra\Testbench\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Project-specific custom expectations go here. Pest's documentation has
| examples: https://pestphp.com/docs/custom-expectations
|
*/

// expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Global helper functions used across multiple test files go here. Prefer
| Pest's higher-order test syntax over loose helpers where possible.
|
*/

/**
 * Absolute path to the repo-init installed as this project's Composer
 * dependency — the version `composer.json` resolved. Tests use it directly
 * rather than `RepoInitLocator`, whose ambient fallbacks (global Composer,
 * cwd, sibling clone) could otherwise make a test depend on the dev machine.
 */
function repoInitPath(): string
{
    return __DIR__ . '/../vendor/sandermuller/repo-init';
}

function readFileContents(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Failed to read {$path}");
    }

    return $contents;
}

/**
 * @return array<mixed>
 */
function composerJsonOf(string $dir): array
{
    $decoded = json_decode(readFileContents($dir . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException("{$dir}/composer.json is not a JSON object");
    }

    return $decoded;
}

/**
 * @return list<string>
 */
function composerPackagesOf(string $dir, string $section): array
{
    $packages = composerJsonOf($dir)[$section] ?? [];

    return is_array($packages) ? array_values(array_filter(array_keys($packages), is_string(...))) : [];
}

function workflowsOf(string $dir): string
{
    $paths = glob($dir . '/.github/workflows/*.yml');

    return implode("\n", array_map(readFileContents(...), $paths === false ? [] : $paths));
}

function phpLints(string $file): bool
{
    $lint = new Process([PHP_BINARY, '-l', $file]);
    $lint->run();

    return $lint->isSuccessful();
}

function removeDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $entries = scandir($dir);
    foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $entry) {
        $path = $dir . '/' . $entry;
        is_dir($path) && ! is_link($path) ? removeDirectory($path) : unlink($path);
    }

    rmdir($dir);
}

/*
|--------------------------------------------------------------------------
| Tia Engine
|--------------------------------------------------------------------------
|
| Tia (test impact analysis) re-runs only the tests that your last change
| touched. `locally()` switches Tia off for any run with `--ci`, so CI keeps
| the full suite. The first recording run needs a coverage driver (PCOV or
| Xdebug); the graph lives in `~/.pest/tia/`, outside the repository.
|
*/

pest()->tia()->locally();

/**
 * Records the composer calls; `install()` can plant a vendor/bin/boost so the
 * post-install boost sync step runs for real.
 */
final class RecordingComposerRunner implements ComposerRunnerInterface
{
    /** @var list<array{packages: list<string>, dev: bool}> */
    public array $requires = [];

    public function __construct(private readonly ?int $boostExitCode = null) {}

    public function install(string $cwd): void
    {
        if ($this->boostExitCode === null) {
            return;
        }

        mkdir($cwd . '/vendor/bin', 0755, true);
        file_put_contents($cwd . '/vendor/bin/boost', "#!/bin/sh\necho 'boom' >&2\nexit {$this->boostExitCode}\n");
        chmod($cwd . '/vendor/bin/boost', 0755);
    }

    public function require(string $cwd, array $packages, bool $dev = false): void
    {
        $this->requires[] = ['packages' => $packages, 'dev' => $dev];
    }

    public function remove(string $cwd, array $packages, bool $noUpdate = false): void {}
}
