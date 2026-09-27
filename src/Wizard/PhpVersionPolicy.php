<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard;

use InvalidArgumentException;

/**
 * Accepted `--php` values per category, per repo-init's
 * references/version-defaults.md "PHP": an application (laravel-project)
 * takes the newest stable PHP only; every package category floors one minor
 * lower and may opt up. The first entry is the default.
 */
final class PhpVersionPolicy
{
    private const array APPLICATION = ['8.5'];

    private const array PACKAGE = ['8.4', '8.5'];

    /**
     * @return non-empty-list<string>
     */
    public static function allowedFor(?string $category): array
    {
        return $category === 'laravel-project' ? self::APPLICATION : self::PACKAGE;
    }

    public static function defaultFor(?string $category): string
    {
        return self::allowedFor($category)[0];
    }

    /**
     * @throws InvalidArgumentException when the version is not accepted for the category
     */
    public static function assertAllowed(string $phpVersion, ?string $category): void
    {
        $allowed = self::allowedFor($category);
        if (in_array($phpVersion, $allowed, true)) {
            return;
        }

        $scope = $category ?? 'package categories';

        throw new InvalidArgumentException(
            '--php must be one of: ' . implode(', ', $allowed) . " for {$scope}, got '{$phpVersion}'. "
            . 'PHP 8.3 and below are no longer supported (repo-init version-defaults.md).',
        );
    }
}
