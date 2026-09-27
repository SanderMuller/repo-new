<?php declare(strict_types=1);

use SanderMuller\RepoNew\Wizard\PhpVersionPolicy;

it("accepts the category's PHP versions", function (string $php, ?string $category): void {
    PhpVersionPolicy::assertAllowed($php, $category);

    expect(PhpVersionPolicy::allowedFor($category))->toContain($php);
})->with([
    ['8.4', 'php-package'],
    ['8.5', 'php-package'],
    ['8.5', 'laravel-project'],
    ['8.4', null],
]);

it("rejects PHP versions outside the category's set", function (string $php, ?string $category, string $scope): void {
    expect(fn () => PhpVersionPolicy::assertAllowed($php, $category))
        ->toThrow(InvalidArgumentException::class, "for {$scope}, got '{$php}'");
})->with([
    ['8.3', 'php-package', 'php-package'],
    ['8.4', 'laravel-project', 'laravel-project'],
    ['8.3', null, 'package categories'],
]);

it('defaults packages to 8.4 and laravel-project to 8.5', function (): void {
    expect(PhpVersionPolicy::defaultFor('php-package'))->toBe('8.4')
        ->and(PhpVersionPolicy::defaultFor('laravel-project'))->toBe('8.5');
});
