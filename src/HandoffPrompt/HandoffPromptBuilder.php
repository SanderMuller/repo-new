<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\HandoffPrompt;

use RuntimeException;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\Wizard\WizardState;

/**
 * Generates the copy-pasteable Claude handoff per spec §7.
 *
 * One template per category lives in src/HandoffPrompt/templates/<cat>.txt
 * with placeholders {TARGET_DIR}, {COMPOSER_NAME}, {NAMESPACE}, etc.
 */
final class HandoffPromptBuilder
{
    private const string TEMPLATE_DIR = __DIR__ . '/templates';

    /**
     * Appended when a Pest scaffold has no commit yet: Pest's Tia engine
     * stops the suite until the repository has one.
     */
    private const string FIRST_COMMIT_NOTE = <<<'TXT'

        The repository has no commit yet. Pest's Tia engine needs one, so
        `composer test` stops until the first commit. Commit the scaffold first,
        or run `vendor/bin/pest --no-tia` for a run before that commit.
        TXT;

    public function build(WizardState $state, string $targetDir, bool $hasCommits = true): string
    {
        $category = $state->category ?? '';
        $template = self::TEMPLATE_DIR . '/' . $category . '.txt';

        if (! is_file($template)) {
            throw new RuntimeException("No handoff template for category {$category}");
        }

        $body = file_get_contents($template);
        if ($body === false) {
            throw new RuntimeException("Failed to read template {$template}");
        }

        $vendorStudly = PlaceholderSubstituter::studly($state->vendor ?? '');
        $packageStudly = PlaceholderSubstituter::studly($state->package ?? '');
        $namespace = "{$vendorStudly}\\{$packageStudly}";

        if (! $hasCommits && $state->testFramework === 'pest') {
            $body = rtrim($body) . "\n" . self::FIRST_COMMIT_NOTE . "\n";
        }

        return strtr($body, [
            '{TARGET_DIR}' => $targetDir,
            '{COMPOSER_NAME}' => $state->composerName() ?? '',
            '{NAMESPACE}' => $namespace,
            '{VENDOR_STUDLY}' => $vendorStudly,
            '{PACKAGE_STUDLY}' => $packageStudly,
            '{CATEGORY}' => $category,
            '{TEST_FRAMEWORK}' => $state->testFramework ?? '',
            '{PHP_VERSION}' => $state->phpVersion ?? '',
            '{LARAVEL_VERSIONS}' => $state->laravelVersions ?? '',
            '{PLUGIN_SHAPE}' => $state->pluginShape ?? '',
            '{VARIANT_DESCRIPTION}' => $state->variant === 'spatie'
                ? 'spatie/laravel-package-tools based'
                : 'plain Illuminate ServiceProvider',
        ]);
    }
}
