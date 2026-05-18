<?php declare(strict_types=1);

use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\RepoInitLocator;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Scaffolder\PackageScaffolder;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class NoopComposerRunnerForPlugin implements ComposerRunnerInterface
{
    public function install(string $cwd): void {}

    public function require(string $cwd, array $packages, bool $dev = false): void {}

    public function remove(string $cwd, array $packages, bool $noUpdate = false): void {}
}

beforeEach(function (): void {
    $repoInit = (new RepoInitLocator())->locate();

    // composer-plugin stubs may not exist on globally-installed repo-init
    // (pre-release feature). Fall back to dev path if peer's repo-init is
    // checked out side-by-side.
    if (! is_dir($repoInit . '/stubs/composer-plugin')) {
        $devPath = dirname(__DIR__, 3) . '/repo-init';
        if (is_dir($devPath . '/stubs/composer-plugin')) {
            $repoInit = $devPath;
        } else {
            $this->markTestSkipped('composer-plugin stubs not present in installed repo-init');
        }
    }

    $this->repoInit = $repoInit;

    $this->tmp = sys_get_temp_dir() . '/repo-new-plugin-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);

    $output = new BufferedOutput();
    $io = new SymfonyStyle(new ArrayInput([]), $output);

    $this->scaffolder = new PackageScaffolder(
        $io,
        new StubReader($repoInit),
        new PerCategoryDeps($repoInit . '/references/per-category-deps.yml'),
        new NoopComposerRunnerForPlugin(),
    );
});

afterEach(function (): void {
    if (! property_exists($this, 'tmp') || $this->tmp === null || ! is_dir($this->tmp)) {
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

function scaffoldPlugin(string $tmp, string $shape, PackageScaffolder $scaffolder): void
{
    $state = new WizardState();
    $state->category = 'composer-plugin';
    $state->vendor = 'sandermuller';
    $state->package = 'my-plugin';
    $state->description = 'Test plugin.';
    $state->phpVersion = '8.3';
    $state->testFramework = 'pest';
    $state->pluginShape = $shape;
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'github@scode.nl';

    $scaffolder->scaffold($state, $tmp);
}

it('keeps only the chosen Plugin variant (none) and drops CommandProvider', function (): void {
    scaffoldPlugin($this->tmp, 'none', $this->scaffolder);

    expect(file_exists($this->tmp . '/src/Plugin.php'))->toBeTrue()
        ->and(file_exists($this->tmp . '/src/Plugin.none.php'))->toBeFalse()
        ->and(file_exists($this->tmp . '/src/Plugin.command-provider.php'))->toBeFalse()
        ->and(file_exists($this->tmp . '/src/Plugin.event-subscriber.php'))->toBeFalse()
        ->and(file_exists($this->tmp . '/src/Plugin.both.php'))->toBeFalse()
        ->and(file_exists($this->tmp . '/src/CommandProvider.php'))->toBeFalse();
});

it('keeps CommandProvider when shape is command-provider', function (): void {
    scaffoldPlugin($this->tmp, 'command-provider', $this->scaffolder);

    expect(file_exists($this->tmp . '/src/Plugin.php'))->toBeTrue()
        ->and(file_exists($this->tmp . '/src/CommandProvider.php'))->toBeTrue();
});

it('keeps CommandProvider when shape is both', function (): void {
    scaffoldPlugin($this->tmp, 'both', $this->scaffolder);

    expect(file_exists($this->tmp . '/src/Plugin.php'))->toBeTrue()
        ->and(file_exists($this->tmp . '/src/CommandProvider.php'))->toBeTrue();
});

it('drops CommandProvider when shape is event-subscriber', function (): void {
    scaffoldPlugin($this->tmp, 'event-subscriber', $this->scaffolder);

    expect(file_exists($this->tmp . '/src/Plugin.php'))->toBeTrue()
        ->and(file_exists($this->tmp . '/src/CommandProvider.php'))->toBeFalse();
});
