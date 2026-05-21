<?php declare(strict_types=1);

use SanderMuller\RepoNew\Scaffolder\SharedStubSkipper;

it('skips an exact stub-relative path match', function (): void {
    $skipper = new SharedStubSkipper(['.mcp.json', 'phpunit.xml']);

    expect($skipper->shouldSkip('.mcp.json'))->toBeTrue()
        ->and($skipper->shouldSkip('phpunit.xml'))->toBeTrue()
        ->and($skipper->shouldSkip('pint.json'))->toBeFalse();
});

it('skips a directory prefix when the entry ends with a slash', function (): void {
    $skipper = new SharedStubSkipper(['tests/']);

    expect($skipper->shouldSkip('tests/Feature/.gitkeep'))->toBeTrue()
        ->and($skipper->shouldSkip('tests'))->toBeFalse()
        ->and($skipper->shouldSkip('src/Foo.php'))->toBeFalse();
});

it('skips nothing for an empty denylist', function (): void {
    $skipper = new SharedStubSkipper([]);

    expect($skipper->shouldSkip('anything'))->toBeFalse();
});
