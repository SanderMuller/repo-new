<?php declare(strict_types=1);

use SanderMuller\RepoNew\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @param  array<string, bool|string>  $input
 */
function runNew(array $input): CommandTester
{
    // Via the Application, so the global --no-interaction option is defined.
    $tester = new CommandTester(new Application()->find('new'));
    $tester->execute($input, ['interactive' => false]);

    return $tester;
}

// Run from a throwaway cwd: a flag combination that slips past validation
// would otherwise scaffold into the repo checkout.
beforeEach(function (): void {
    $this->previousCwd = (string) getcwd();
    $this->tmp = sys_get_temp_dir() . '/repo-new-cmd-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);
    chdir($this->tmp);
});

afterEach(function (): void {
    chdir($this->previousCwd);
    @rmdir($this->tmp);
});

it('rejects an unsupported --php before scaffolding anything', function (string $type, string $php): void {
    $tester = runNew([
        'name' => 'acme/demo',
        '--type' => $type,
        '--description' => 'Demo.',
        '--php' => $php,
        '--no-interaction' => true,
    ]);

    expect($tester->getStatusCode())->toBe(65)
        ->and($tester->getDisplay())->toContain('--php must be one of')
        ->and(scandir($this->tmp))->toBe(['.', '..']);
})->with([
    'php 8.3 for a package' => ['php-package', '8.3'],
    'php 8.4 for a laravel-project' => ['laravel-project', '8.4'],
    'garbage' => ['php-package', 'latest'],
]);

it('rejects an unknown --variant', function (): void {
    $tester = runNew([
        'name' => 'acme/demo',
        '--type' => 'laravel-package',
        '--description' => 'Demo.',
        '--variant' => 'plain',
        '--no-interaction' => true,
    ]);

    expect($tester->getStatusCode())->toBe(65)
        ->and($tester->getDisplay())->toContain('--variant must be one of')
        ->and(scandir($this->tmp))->toBe(['.', '..']);
});

it('warns that --with-security-advisories is a deprecated no-op', function (): void {
    // --php=8.3 stops the run right after the warning, before any scaffolding.
    $tester = runNew([
        'name' => 'acme/demo',
        '--type' => 'php-package',
        '--description' => 'Demo.',
        '--php' => '8.3',
        '--with-security-advisories' => true,
        '--no-interaction' => true,
    ]);

    expect($tester->getDisplay())->toContain('--with-security-advisories is deprecated')
        ->and($tester->getStatusCode())->toBe(65);
});

it('re-checks --php after the wizard picks the category', function (): void {
    $tester = new CommandTester(new Application()->find('new'));
    $tester->setInputs(['project']);
    $tester->execute(['name' => 'acme/demo', '--description' => 'Demo.', '--php' => '8.4']);

    expect($tester->getStatusCode())->toBe(65)
        ->and($tester->getDisplay())->toContain('for laravel-project')
        ->and(scandir($this->tmp))->toBe(['.', '..']);
});

it('rejects pest for a laravel-project picked in the wizard', function (): void {
    $tester = new CommandTester(new Application()->find('new'));
    $tester->setInputs(['project']);
    $tester->execute(['name' => 'acme/demo', '--description' => 'Demo.', '--test-framework' => 'pest']);

    expect($tester->getStatusCode())->toBe(65)
        ->and($tester->getDisplay())->toContain('does not yet support --test-framework=pest')
        ->and(scandir($this->tmp))->toBe(['.', '..']);
});

it('rejects a Pest laravel-package whose Laravel range excludes 13', function (string $range): void {
    $tester = runNew([
        'name' => 'acme/demo',
        '--type' => 'laravel-package',
        '--description' => 'Demo.',
        '--laravel' => $range,
        '--test-framework' => 'pest',
        '--no-interaction' => true,
    ]);

    expect($tester->getStatusCode())->toBe(65)
        ->and($tester->getDisplay())->toContain('cannot be tested with Pest 5')
        ->and(scandir($this->tmp))->toBe(['.', '..']);
})->with(['^12.0', '^12.13']);

it('rejects an unknown --license', function (): void {
    $tester = runNew([
        'name' => 'acme/demo',
        '--type' => 'php-package',
        '--description' => 'Demo.',
        '--license' => 'GPL',
        '--no-interaction' => true,
    ]);

    expect($tester->getStatusCode())->toBe(65)
        ->and($tester->getDisplay())->toContain('--license must be one of')
        ->and(scandir($this->tmp))->toBe(['.', '..']);
});
