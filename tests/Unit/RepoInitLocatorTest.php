<?php declare(strict_types=1);

use SanderMuller\RepoNew\RepoInit\RepoInitLocator;

it('locates a repo-init directory with the expected layout', function (): void {
    $repoInit = (new RepoInitLocator())->locate();

    expect($repoInit)
        ->toBeDirectory();
});

it("resolves repo-new's own pinned repo-init, not an ambient global", function (): void {
    // The locator must prefer repo-new's own resolved dependency over any
    // stale global install — i.e. the same directory the test helper pins to.
    expect(realpath((new RepoInitLocator())->locate()))
        ->toBe(realpath(repoInitPath()));
});
