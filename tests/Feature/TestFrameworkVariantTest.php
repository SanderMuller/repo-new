<?php declare(strict_types=1);

use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Scaffolder\PackageScaffolder;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function variantScaffolder(ComposerRunnerInterface $composer): PackageScaffolder
{
    $repoInit = repoInitPath();

    return new PackageScaffolder(
        new SymfonyStyle(new ArrayInput([]), new BufferedOutput()),
        new StubReader($repoInit),
        new PerCategoryDeps($repoInit . '/references/per-category-deps.yml'),
        $composer,
    );
}

/**
 * @param  array{phpVersion?: string, variant?: string, laravelAware?: bool}  $overrides
 */
function variantState(string $category, string $framework, array $overrides = []): WizardState
{
    $state = new WizardState();
    $state->category = $category;
    $state->vendor = 'sandermuller';
    $state->package = 'demo-thing';
    $state->description = 'Demo.';
    $state->phpVersion = $overrides['phpVersion'] ?? '8.4';
    $state->testFramework = $framework;
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'github@scode.nl';
    $state->variant = $overrides['variant'] ?? null;
    $state->laravelAware = $overrides['laravelAware'] ?? false;

    $state->applyDefaults();

    return $state;
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-variant-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);
});

afterEach(function (): void {
    removeDirectory($this->tmp);
});

it('composes pest onto the PHPUnit-flavoured spatie laravel-package stub', function (): void {
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('laravel-package', 'pest', ['variant' => 'spatie']), $this->tmp);

    $ci = readFileContents($this->tmp . '/.github/workflows/run-tests.yml');

    expect(composerJsonOf($this->tmp))
        ->not->toHaveKey('require-dev.phpunit/phpunit')
        ->toHaveKey('require-dev.pestphp/pest', '^5.0')
        ->toHaveKey('require-dev.pestphp/pest-plugin-laravel')
        ->toHaveKey('require-dev.orchestra/testbench', '^11.0')
        ->toHaveKey('config.allow-plugins.pestphp/pest-plugin', true)
        ->toHaveKey('scripts.test', 'vendor/bin/pest')
        ->toHaveKey('require.spatie/laravel-package-tools')
        ->and($ci)->toContain('run: vendor/bin/pest --ci')
        ->and($ci)->not->toContain("laravel: '12.*'")
        ->and(readFileContents($this->tmp . '/rector.php'))->not->toContain('withComposerBased')
        ->and($this->tmp . '/tests/Pest.php')->toBeFile();
});

it('composes phpunit onto the Pest-flavoured sander laravel-package stub', function (): void {
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('laravel-package', 'phpunit', ['variant' => 'sander']), $this->tmp);

    $ci = readFileContents($this->tmp . '/.github/workflows/run-tests.yml');
    $pestPackages = array_filter(composerPackagesOf($this->tmp, 'require-dev'), static fn (string $p): bool => str_starts_with($p, 'pestphp/'));

    expect($pestPackages)->toBeEmpty()
        ->and(composerJsonOf($this->tmp))
        ->toHaveKey('require-dev.phpunit/phpunit', '^11.0||^12.0')
        ->toHaveKey('require-dev.orchestra/testbench', '^10.0||^11.0')
        ->not->toHaveKey('config.allow-plugins.pestphp/pest-plugin')
        ->toHaveKey('scripts.test', 'vendor/bin/phpunit')
        ->and($ci)->toContain('run: vendor/bin/phpunit')
        ->and($ci)->toContain("laravel: '12.*'")
        ->and(readFileContents($this->tmp . '/rector.php'))->toContain('->withComposerBased(phpunit: true)')
        ->and(phpLints($this->tmp . '/rector.php'))->toBeTrue()
        ->and($this->tmp . '/tests/Pest.php')->not->toBeFile();
});

it('composes phpunit onto the Pest-flavoured php-package stub', function (): void {
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('php-package', 'phpunit'), $this->tmp);

    $rector = readFileContents($this->tmp . '/rector.php');

    expect(array_filter(composerPackagesOf($this->tmp, 'require-dev'), static fn (string $p): bool => str_starts_with($p, 'pestphp/')))
        ->toBeEmpty()
        ->and(composerJsonOf($this->tmp))
        ->toHaveKey('require-dev.phpunit/phpunit')
        ->not->toHaveKey('config.allow-plugins.pestphp/pest-plugin')
        ->toHaveKey('scripts.test-coverage', 'vendor/bin/phpunit --coverage-html=coverage')
        ->and(readFileContents($this->tmp . '/.github/workflows/run-tests.yml'))->toContain('run: vendor/bin/phpunit')
        ->and(substr_count($rector, '->withComposerBased(phpunit: true)'))->toBe(1)
        ->and(phpLints($this->tmp . '/rector.php'))->toBeTrue();
});

it('composes pest onto the PHPUnit-flavoured rector-extension stub', function (): void {
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('rector-extension', 'pest'), $this->tmp);

    expect(composerJsonOf($this->tmp))
        ->not->toHaveKey('require-dev.phpunit/phpunit')
        ->toHaveKey('require-dev.pestphp/pest')
        ->toHaveKey('require-dev.orchestra/testbench', '^11.0')
        ->toHaveKey('scripts.test', 'vendor/bin/pest')
        ->toHaveKey('config.allow-plugins.rector/extension-installer', true)
        ->and(readFileContents($this->tmp . '/.github/workflows/run-tests.yml'))->toContain('run: vendor/bin/pest --ci')
        ->and(readFileContents($this->tmp . '/rector.php'))->not->toContain('withComposerBased');
});

it('runs the stub framework in CI even when the stub ships the other command', function (): void {
    // rector-extension's stub is PHPUnit-only, but its run-tests.yml runs pest.
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('rector-extension', 'phpunit'), $this->tmp);

    expect(readFileContents($this->tmp . '/.github/workflows/run-tests.yml'))
        ->toContain('run: vendor/bin/phpunit')
        ->not->toContain('vendor/bin/pest')
        ->toEndWith("\n");
});

it('moves every pinned PHP 8.4 in the workflows to 8.5 for --php=8.5', function (): void {
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('php-package', 'pest', ['phpVersion' => '8.5']), $this->tmp);

    $workflows = workflowsOf($this->tmp);

    expect($workflows)->not->toContain("'8.4'")
        ->and($workflows)->toContain("php: '8.5'")
        ->and($workflows)->toContain("php-version: '8.5'")
        ->and(composerJsonOf($this->tmp))->toHaveKey('require.php', '^8.5');
});

it('does not re-require unconstrained deps the stub already pins', function (): void {
    $composer = new RecordingComposerRunner();
    variantScaffolder($composer)->scaffold(variantState('php-package', 'pest'), $this->tmp);

    $requested = array_merge(...array_column($composer->requires, 'packages'));

    // orchestra/testbench is bare in per-category-deps.yml and pinned ^11.0 by the stub.
    // symplify/phpstan-rules is constrained in per-category-deps.yml, so it is still requested.
    expect($requested)->not->toContain('orchestra/testbench')
        ->and(array_filter($requested, static fn (string $entry): bool => str_starts_with($entry, 'symplify/phpstan-rules: ^')))->not->toBeEmpty();
});

it('fails the scaffold when boost sync fails', function (): void {
    variantScaffolder(new RecordingComposerRunner(boostExitCode: 3))
        ->scaffold(variantState('php-package', 'pest'), $this->tmp);
})->throws(RuntimeException::class, 'boost sync failed (exit 3)');

it('still requests an unconstrained dep the stub does not pin', function (): void {
    $composer = new RecordingComposerRunner();
    variantScaffolder($composer)->scaffold(variantState('phpstan-extension', 'phpunit', ['laravelAware' => true]), $this->tmp);

    // larastan/larastan is bare in the laravel-aware opt-in and absent from the stub.
    expect(array_merge(...array_column($composer->requires, 'packages')))->toContain('larastan/larastan');
});

it('does not request spatie/laravel-package-tools for the sander variant', function (): void {
    $composer = new RecordingComposerRunner();
    variantScaffolder($composer)->scaffold(variantState('laravel-package', 'pest', ['variant' => 'sander']), $this->tmp);

    expect(array_merge(...array_column($composer->requires, 'packages')))
        ->not->toContain('spatie/laravel-package-tools');
});

it('pins the swapped-in laravel-package CI matrix to 8.5 for --php=8.5', function (): void {
    variantScaffolder(new RecordingComposerRunner())
        ->scaffold(variantState('laravel-package', 'pest', ['variant' => 'spatie', 'phpVersion' => '8.5']), $this->tmp);

    expect(workflowsOf($this->tmp))->not->toContain("'8.4'")
        ->toContain("php: '8.5'");
});
