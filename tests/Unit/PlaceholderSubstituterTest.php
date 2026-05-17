<?php declare(strict_types=1);

use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\Wizard\WizardState;

it('substitutes all twelve placeholders for sandermuller/queue-insights', function (): void {
    $state = new WizardState();
    $state->vendor = 'sandermuller';
    $state->package = 'queue-insights';
    $state->description = 'A test package';
    $state->authorName = 'Sander Muller';
    $state->authorEmail = 'sander@example.com';
    $state->phpVersion = '8.3';
    $state->laravelVersions = '^11.0||^12.0||^13.0';

    $sub = new PlaceholderSubstituter($state);

    expect($sub->substitute('__VENDOR__'))->toBe('sandermuller')
        ->and($sub->substitute('__PACKAGE__'))->toBe('queue-insights')
        ->and($sub->substitute('__VENDOR_STUDLY__'))->toBe('Sandermuller')
        ->and($sub->substitute('__PACKAGE_STUDLY__'))->toBe('QueueInsights')
        ->and($sub->substitute('__NAMESPACE__'))->toBe('Sandermuller\\QueueInsights')
        ->and($sub->substitute('__NAMESPACE_ESCAPED__'))->toBe('Sandermuller\\\\QueueInsights')
        ->and($sub->substitute('__DESCRIPTION__'))->toBe('A test package')
        ->and($sub->substitute('__AUTHOR_NAME__'))->toBe('Sander Muller')
        ->and($sub->substitute('__AUTHOR_EMAIL__'))->toBe('sander@example.com')
        ->and($sub->substitute('__PHP_VERSION__'))->toBe('^8.3')
        ->and($sub->substitute('__LARAVEL_VERSIONS__'))->toBe('^11.0||^12.0||^13.0')
        ->and($sub->substitute('__PHP_VERSION_NEON__'))->toBe('83')
        ->and((int) $sub->substitute('__YEAR__'))->toBeGreaterThanOrEqual(2024);
});

it('substitutes placeholders inside file paths', function (): void {
    $state = new WizardState();
    $state->vendor = 'sandermuller';
    $state->package = 'queue-insights';

    $sub = new PlaceholderSubstituter($state);

    expect($sub->substitute('src/__PACKAGE_STUDLY__ServiceProvider.php'))
        ->toBe('src/QueueInsightsServiceProvider.php')
        ->and($sub->substitute('config/__PACKAGE__.php'))->toBe('config/queue-insights.php');
});

it('substitutes placeholders inside JSON content with escaped namespace', function (): void {
    $state = new WizardState();
    $state->vendor = 'sandermuller';
    $state->package = 'queue-insights';

    $sub = new PlaceholderSubstituter($state);

    $input = '{"psr-4": {"__NAMESPACE_ESCAPED__\\\\": "src/"}}';
    $output = $sub->substitute($input);

    expect($output)->toContain('Sandermuller\\\\QueueInsights');
});

it('StudlyCase handles mixed separators, digits, and all-caps per rules', function (): void {
    expect(PlaceholderSubstituter::studly('queue-insights'))->toBe('QueueInsights')
        ->and(PlaceholderSubstituter::studly('laravel_js_store'))->toBe('LaravelJsStore')
        ->and(PlaceholderSubstituter::studly('sandermuller'))->toBe('Sandermuller')
        ->and(PlaceholderSubstituter::studly('SanderMuller'))->toBe('Sandermuller')
        ->and(PlaceholderSubstituter::studly('my-package_name'))->toBe('MyPackageName')
        ->and(PlaceholderSubstituter::studly('HiHaHo'))->toBe('Hihaho');
});
