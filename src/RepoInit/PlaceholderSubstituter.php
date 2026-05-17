<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\RepoInit;

use SanderMuller\RepoNew\Wizard\WizardState;

/**
 * Applies the placeholder transforms documented in repo-init's
 * references/placeholder-rules.md.
 *
 * Same substitution table for file content AND file paths.
 */
final class PlaceholderSubstituter
{
    /** @var array<string, string> */
    private readonly array $replacements;

    public function __construct(WizardState $state)
    {
        $vendor = $state->vendor ?? '';
        $package = $state->package ?? '';
        $vendorStudly = self::studly($vendor);
        $packageStudly = self::studly($package);
        $namespace = "{$vendorStudly}\\{$packageStudly}";
        // JSON-source form: each `\` in the namespace is doubled so json_decode
        // produces a single `\`. Result is 2 backslashes raw.
        $namespaceEscaped = "{$vendorStudly}\\\\{$packageStudly}";
        // NOTE: the literal string above contains 2 backslashes raw (PHP `\\\\`
        // = 2 characters), which is exactly the JSON-source form we need.

        $phpVersion = $state->phpVersion ?? '8.3';

        $this->replacements = [
            '__VENDOR__' => $vendor,
            '__PACKAGE__' => $package,
            '__VENDOR_STUDLY__' => $vendorStudly,
            '__PACKAGE_STUDLY__' => $packageStudly,
            '__NAMESPACE_ESCAPED__' => $namespaceEscaped,
            '__NAMESPACE__' => $namespace,
            '__DESCRIPTION__' => $state->description ?? '',
            '__AUTHOR_NAME__' => $state->authorName ?? '',
            '__AUTHOR_EMAIL__' => $state->authorEmail ?? '',
            '__PHP_VERSION__' => "^{$phpVersion}",
            '__LARAVEL_VERSIONS__' => $state->laravelVersions ?? '',
            '__PHP_VERSION_NEON__' => str_replace('.', '', $phpVersion),
            '__YEAR__' => (string) (int) date('Y'),
        ];
    }

    public function substitute(string $input): string
    {
        // Order matters: __NAMESPACE_ESCAPED__ must replace before
        // __NAMESPACE__ (longer first). The replacements array is built
        // in long-first order; reinforce via strtr which does single-pass
        // multi-pattern replacement.
        return strtr($input, $this->replacements);
    }

    /**
     * StudlyCase per placeholder-rules.md:
     *  1. Split on `-` and `_`.
     *  2. Lowercase each part.
     *  3. Uppercase first letter of each part.
     *  4. Concatenate.
     */
    public static function studly(string $input): string
    {
        $parts = preg_split('/[-_]+/', $input);
        if ($parts === false) {
            $parts = [];
        }
        $out = '';
        foreach ($parts as $part) {
            $part = strtolower($part);
            if ($part === '') {
                continue;
            }
            $out .= ucfirst($part);
        }

        return $out;
    }

    /** @return array<string, string> */
    public function replacements(): array
    {
        return $this->replacements;
    }
}
