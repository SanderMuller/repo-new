<?php declare(strict_types=1);

use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Scaffolder\LaravelProjectScaffolder;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function laravelSkeletonBootstrap(): string
{
    return <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

PHP;
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-project-' . bin2hex(random_bytes(4));
    mkdir($this->tmp . '/bootstrap', 0755, true);

    // A minimal stand-in for what `laravel new --boost` leaves behind.
    file_put_contents($this->tmp . '/composer.json', json_encode([
        'name' => 'laravel/laravel',
        'require' => ['php' => '^8.3', 'laravel/framework' => '^13.0', 'laravel/tinker' => '^3.0'],
        'require-dev' => ['phpunit/phpunit' => '^12.0'],
        'config' => ['allow-plugins' => ['pestphp/pest-plugin' => true, 'php-http/discovery' => true]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($this->tmp . '/bootstrap/app.php', laravelSkeletonBootstrap());
    file_put_contents($this->tmp . '/README.md', "# Laravel skeleton README\n");
    file_put_contents($this->tmp . '/boost.json', '{"agents":["claude_code"]}');
    file_put_contents($this->tmp . '/.mcp.json', '{"mcpServers":{"laravel-boost":{}}}');

    $this->composer = new RecordingComposerRunner();
    $this->output = new BufferedOutput();
    $repoInit = repoInitPath();
    $this->scaffolder = new LaravelProjectScaffolder(
        new SymfonyStyle(new ArrayInput([]), $this->output),
        new StubReader($repoInit),
        new PerCategoryDeps($repoInit . '/references/per-category-deps.yml'),
        $this->composer,
    );

    $state = new WizardState();
    $state->category = 'laravel-project';
    $state->vendor = 'acme';
    $state->package = 'shop';
    $state->description = 'Shop.';
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'github@scode.nl';
    $state->applyDefaults();
    $this->state = $state;
});

afterEach(function (): void {
    removeDirectory($this->tmp);
});

it('installs the security-canon runtime deps into require', function (): void {
    $this->scaffolder->overlay($this->state, $this->tmp);

    $runtime = array_values(array_filter($this->composer->requires, static fn (array $call): bool => ! $call['dev']));

    expect($runtime)->toHaveCount(1)
        ->and($runtime[0]['packages'])->toContain('zae/strict-transport-security')
        ->not->toContain('spatie/security-advisories-health-check');
});

it('adds the hihaho rule packs by default for vendor hihaho, and not when turned off', function (?bool $flag, bool $expected): void {
    $this->state->vendor = 'hihaho';
    $this->state->withHihahoRules = $flag;
    $this->state->applyDefaults();

    $this->scaffolder->overlay($this->state, $this->tmp);

    $dev = array_merge(...array_column(array_filter($this->composer->requires, static fn (array $call): bool => $call['dev']), 'packages'));

    expect(in_array('hihaho/phpstan-rules', $dev, true))->toBe($expected)
        ->and(in_array('hihaho/rector-rules', $dev, true))->toBe($expected)
        ->and(str_contains(readFileContents($this->tmp . '/phpstan.neon.dist'), "\n    - vendor/hihaho/phpstan-rules/extension.neon\n"))->toBe($expected)
        ->and(str_contains(readFileContents($this->tmp . '/rector.php'), '\Hihaho\RectorRules\Set\HihahoSetList::ALL,'))->toBe($expected)
        ->and(phpLints($this->tmp . '/rector.php'))->toBeTrue();
})->with([
    'vendor default' => [null, true],
    '--no-with-hihaho-rules' => [false, false],
]);

it('adds the health-check package only with --with-health-checks', function (): void {
    $this->state->withHealthChecks = true;

    $this->scaffolder->overlay($this->state, $this->tmp);

    $runtime = array_merge(...array_column(array_filter($this->composer->requires, static fn (array $call): bool => ! $call['dev']), 'packages'));

    expect($runtime)->toContain('spatie/security-advisories-health-check');
});

it('appends SecurityHeaders and HSTS to the global middleware stack', function (): void {
    $this->scaffolder->overlay($this->state, $this->tmp);

    $bootstrap = readFileContents($this->tmp . '/bootstrap/app.php');

    expect($bootstrap)->toContain('$middleware->append([')
        ->and($bootstrap)->toContain('\App\Http\Middleware\SecurityHeaders::class')
        ->and($bootstrap)->toContain('\Zae\StrictTransportSecurity\Middleware\L5\StrictTransportSecurity::class')
        ->and(phpLints($this->tmp . '/bootstrap/app.php'))->toBeTrue()
        ->and($this->tmp . '/app/Http/Middleware/SecurityHeaders.php')->toBeFile();
});

it('keeps the boost.json and .mcp.json that laravel new --boost wrote', function (): void {
    $this->scaffolder->overlay($this->state, $this->tmp);

    expect(file_get_contents($this->tmp . '/boost.json'))->toBe('{"agents":["claude_code"]}')
        ->and(file_get_contents($this->tmp . '/.mcp.json'))->toBe('{"mcpServers":{"laravel-boost":{}}}');
});

it('appends README.append.md to the existing README once, and writes no README.append.md', function (): void {
    $this->scaffolder->overlay($this->state, $this->tmp);
    $this->scaffolder->overlay($this->state, $this->tmp);

    $readme = readFileContents($this->tmp . '/README.md');

    expect($readme)->toStartWith('# Laravel skeleton README')
        ->and(substr_count($readme, '## Code-quality tooling'))->toBe(1)
        ->and($this->tmp . '/README.append.md')->not->toBeFile();
});

it('raises require.php to the laravel-project floor and pins workflows to it', function (): void {
    $this->scaffolder->overlay($this->state, $this->tmp);

    expect(composerJsonOf($this->tmp))
        ->toHaveKey('require.php', '^8.5')
        ->toHaveKey('name', 'acme/shop')
        ->and(workflowsOf($this->tmp))->not->toContain("'8.4'");
});

it('refuses to flip an explicitly denied Composer plugin', function (): void {
    file_put_contents($this->tmp . '/composer.json', json_encode([
        'name' => 'laravel/laravel',
        'require' => ['php' => '^8.3', 'laravel/framework' => '^13.0'],
        'config' => ['allow-plugins' => ['phpstan/extension-installer' => false]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->scaffolder->overlay($this->state, $this->tmp);
})->throws(RuntimeException::class, 'does not override an explicit denial');

it('warns instead of failing when bootstrap/app.php has no empty middleware block', function (): void {
    file_put_contents($this->tmp . '/bootstrap/app.php', "<?php\n\nreturn require __DIR__ . '/custom.php';\n");
    $this->scaffolder->overlay($this->state, $this->tmp);

    expect($this->output->fetch())->toContain('Security middleware NOT registered')
        ->and(readFileContents($this->tmp . '/bootstrap/app.php'))->not->toContain('StrictTransportSecurity');
});

it('writes the stub boost.json when laravel new did not, but never the testbench .mcp.json', function (): void {
    unlink($this->tmp . '/boost.json');
    unlink($this->tmp . '/.mcp.json');

    $this->scaffolder->overlay($this->state, $this->tmp);

    expect($this->tmp . '/boost.json')->toBeFile()
        ->and($this->tmp . '/.mcp.json')->not->toBeFile();
});

it('completes a partial middleware registration', function (): void {
    file_put_contents($this->tmp . '/bootstrap/app.php', str_replace(
        "        //\n    })\n    ->withExceptions",
        "        //\n    })\n    // StrictTransportSecurity::class already mentioned\n    ->withExceptions",
        laravelSkeletonBootstrap(),
    ));

    $this->scaffolder->overlay($this->state, $this->tmp);

    expect(readFileContents($this->tmp . '/bootstrap/app.php'))->toContain('\App\Http\Middleware\SecurityHeaders::class');
});
