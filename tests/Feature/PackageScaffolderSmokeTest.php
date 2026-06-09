<?php declare(strict_types=1);

use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Scaffolder\PackageScaffolder;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A no-op stand-in so we can exercise stub-copy without actually running
 * `composer install` (slow + requires network).
 */
final class NoopComposerRunner implements ComposerRunnerInterface
{
    public function install(string $cwd): void {}

    public function require(string $cwd, array $packages, bool $dev = false): void {}

    public function remove(string $cwd, array $packages, bool $noUpdate = false): void {}
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-smoke-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);

    $repoInit = repoInitPath();
    $output = new BufferedOutput();
    $io = new SymfonyStyle(new ArrayInput([]), $output);

    $this->scaffolder = new PackageScaffolder(
        $io,
        new StubReader($repoInit),
        new PerCategoryDeps($repoInit . '/references/per-category-deps.yml'),
        new NoopComposerRunner(),
    );
});

afterEach(function (): void {
    if (! is_dir($this->tmp)) {
        return;
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iter as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }

    @rmdir($this->tmp);
});

it('scaffolds a php-package skeleton with all expected files', function (): void {
    $state = new WizardState();
    $state->category = 'php-package';
    $state->vendor = 'sandermuller';
    $state->package = 'queue-insights';
    $state->description = 'Queue insights for Laravel.';
    $state->phpVersion = '8.3';
    $state->testFramework = 'pest';
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'github@scode.nl';

    $result = $this->scaffolder->scaffold($state, $this->tmp);

    expect(file_exists($this->tmp . '/composer.json'))->toBeTrue()
        ->and(file_exists($this->tmp . '/src/QueueInsights.php'))->toBeTrue()
        ->and(file_exists($this->tmp . '/.editorconfig'))->toBeTrue()
        ->and(file_exists($this->tmp . '/.gitignore'))->toBeTrue()
        ->and(file_exists($this->tmp . '/phpstan.neon.dist'))->toBeTrue()
        ->and(file_exists($this->tmp . '/.github/workflows/run-tests.yml'))->toBeTrue();

    $composer = json_decode(file_get_contents($this->tmp . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($composer['name'])->toBe('sandermuller/queue-insights')
        ->and($composer['description'])->toBe('Queue insights for Laravel.')
        ->and(array_key_first($composer['autoload']['psr-4']))->toBe('Sandermuller\\QueueInsights\\');

    $src = file_get_contents($this->tmp . '/src/QueueInsights.php');
    expect($src)->toContain('namespace Sandermuller\\QueueInsights;')
        ->and($src)->toContain('final class QueueInsights')
        ->and($result['stubsWritten'])
        ->toBeGreaterThan(5);
});

it('scaffolds a laravel-package with the spatie/laravel-package-tools ServiceProvider', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-package';
    $state->vendor = 'sandermuller';
    $state->package = 'queue-insights';
    $state->description = 'Queue insights for Laravel.';
    $state->phpVersion = '8.3';
    $state->laravelVersions = '^11.0||^12.0||^13.0';
    $state->testFramework = 'pest';
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'github@scode.nl';

    $this->scaffolder->scaffold($state, $this->tmp);

    $providerPath = $this->tmp . '/src/QueueInsightsServiceProvider.php';
    expect(file_exists($providerPath))->toBeTrue()
        ->and(file_exists($this->tmp . '/config/queue-insights.php'))->toBeTrue()
        ->and(file_get_contents($providerPath))->toContain('PackageServiceProvider');

    $composer = json_decode(file_get_contents($this->tmp . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($composer['require']['illuminate/contracts'])->toBe('^11.0||^12.0||^13.0')
        ->and($composer['require'])->toHaveKey('spatie/laravel-package-tools');
});

it('scaffolds a skill-bundle with the lean shared set and no PHP toolchain', function (): void {
    $state = new WizardState();
    $state->category = 'skill-bundle';
    $state->vendor = 'sandermuller';
    $state->package = 'my-skills';
    $state->description = 'A bundle of AI skills.';
    $state->phpVersion = '8.3';
    $state->testFramework = 'pest'; // even with pest set, skill-bundle gets no test-framework overlay
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'github@scode.nl';

    $this->scaffolder->scaffold($state, $this->tmp);

    expect(file_exists($this->tmp . '/composer.json'))->toBeTrue()
        ->and(file_exists($this->tmp . '/resources/boost/skills/.gitkeep'))->toBeTrue()
        // lean shared meta files are kept
        ->and(file_exists($this->tmp . '/pint.json'))->toBeTrue()
        ->and(file_exists($this->tmp . '/.editorconfig'))->toBeTrue()
        // skill-bundle carries boost-core, so it gets the shared .config/boost.php
        ->and(file_exists($this->tmp . '/.config/boost.php'))->toBeTrue()
        // …and NOT at the legacy root path — both present trips boost-core's AmbiguousBoostConfigException
        ->and(file_exists($this->tmp . '/boost.php'))->toBeFalse()
        ->and(file_exists($this->tmp . '/.github/workflows/pint-check.yml'))->toBeTrue()
        ->and(file_exists($this->tmp . '/.github/workflows/update-changelog.yml'))->toBeTrue()
        // PHP-toolchain shared stubs are skipped
        ->and(file_exists($this->tmp . '/phpstan-baseline.neon'))->toBeFalse()
        ->and(file_exists($this->tmp . '/phpunit.xml'))->toBeFalse()
        ->and(file_exists($this->tmp . '/.mcp.json'))->toBeFalse()
        ->and(file_exists($this->tmp . '/.github/workflows/phpstan.yml'))->toBeFalse()
        ->and(file_exists($this->tmp . '/.github/workflows/rector-check.yml'))->toBeFalse()
        // no test-framework overlay, no shared tests/ dir
        ->and(file_exists($this->tmp . '/tests/Pest.php'))->toBeFalse()
        ->and(is_dir($this->tmp . '/tests'))->toBeFalse();

    $composer = json_decode(file_get_contents($this->tmp . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($composer['name'])->toBe('sandermuller/my-skills')
        ->and($composer['require'])->toHaveKey('sandermuller/boost-core')
        // no plugin pre-allow needed — boost-core 0.6+ is type:library, skill-bundle uses no phpstan/pest plugins
        ->and($composer['config'])->not->toHaveKey('allow-plugins');

    // __SKILL_TAGS__ substituted — no skillTags set on the state → empty withTags([]).
    expect(file_get_contents($this->tmp . '/.config/boost.php'))->toContain('->withTags([])');
});
