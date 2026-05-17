<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\HandoffPrompt;

use RuntimeException;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\Wizard\WizardState;

/**
 * Generates the copy-pasteable Claude handoff per spec §7.
 *
 * One template per category lives in src/HandoffPrompt/templates/<cat>.txt
 * with placeholders {TARGET_DIR}, {COMPOSER_NAME}, {NAMESPACE}, {VARIANT}.
 */
final class HandoffPromptBuilder
{
    private const string TEMPLATE_DIR = __DIR__ . '/templates';

    public function build(WizardState $state, string $targetDir): string
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

        $variantLabel = $state->category === 'laravel-package'
            ? ($state->variant ?? 'sander') . ' variant'
            : '';

        return strtr($body, [
            '{TARGET_DIR}' => $targetDir,
            '{COMPOSER_NAME}' => $state->composerName() ?? '',
            '{NAMESPACE}' => $namespace,
            '{VARIANT}' => $variantLabel,
            '{CATEGORY}' => $category,
            '{TEST_FRAMEWORK}' => $state->testFramework ?? '',
            '{PHP_VERSION}' => $state->phpVersion ?? '',
            '{LARAVEL_VERSIONS}' => $state->laravelVersions ?? '',
        ]);
    }
}
