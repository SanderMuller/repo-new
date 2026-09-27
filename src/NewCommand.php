<?php declare(strict_types=1);

namespace SanderMuller\RepoNew;

use InvalidArgumentException;
use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerFailureSurfacer;
use SanderMuller\RepoNew\Composer\ComposerRunner;
use SanderMuller\RepoNew\Git\GitInitializer;
use SanderMuller\RepoNew\HandoffPrompt\HandoffPromptBuilder;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\RepoInitLocator;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Scaffolder\LaravelProjectScaffolder;
use SanderMuller\RepoNew\Scaffolder\PackageScaffolder;
use SanderMuller\RepoNew\Scaffolder\Scaffolder;
use SanderMuller\RepoNew\Scaffolder\TargetDirResolver;
use SanderMuller\RepoNew\Wizard\PhpVersionPolicy;
use SanderMuller\RepoNew\Wizard\Question\SkillTagsQuestion;
use SanderMuller\RepoNew\Wizard\Question\VariantQuestion;
use SanderMuller\RepoNew\Wizard\Wizard;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'new',
    description: 'Scaffold a fresh repo using sandermuller/repo-init.',
)]
final class NewCommand extends Command
{
    private const array CATEGORIES = [
        'laravel-project',
        'laravel-package',
        'php-package',
        'phpstan-extension',
        'rector-extension',
        'composer-plugin',
        'skill-bundle',
    ];

    private const array PLUGIN_SHAPES = ['command-provider', 'event-subscriber', 'both', 'none'];

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Target dir / package name (kebab-case).')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Category: ' . implode(', ', self::CATEGORIES))
            ->addOption('vendor', null, InputOption::VALUE_REQUIRED, 'Composer vendor.')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'One-line description.')
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'PHP version: 8.4|8.5 for packages (default 8.4); 8.5 for laravel-project')
            ->addOption('laravel', null, InputOption::VALUE_REQUIRED, 'Laravel constraint (laravel-package only).')
            ->addOption('test-framework', null, InputOption::VALUE_REQUIRED, 'pest|phpunit')
            ->addOption('variant', null, InputOption::VALUE_REQUIRED, 'laravel-package service provider base: ' . implode('|', VariantQuestion::VARIANTS) . ' (default spatie for vendor hihaho, else sander)')
            ->addOption('with-hihaho-rules', null, InputOption::VALUE_NEGATABLE, 'laravel-project: hihaho PHPStan/Rector rule packs (default on for vendor hihaho).')
            ->addOption('with-health-checks', null, InputOption::VALUE_NONE, 'laravel-project: add spatie/security-advisories-health-check.')
            ->addOption('with-security-advisories', null, InputOption::VALUE_NONE, 'Deprecated, does nothing (removed in the next major). See --with-health-checks.')
            ->addOption('laravel-aware', null, InputOption::VALUE_NONE, 'Opt-in for phpstan/rector-extension.')
            ->addOption('plugin-shape', null, InputOption::VALUE_REQUIRED, 'composer-plugin shape: ' . implode('|', self::PLUGIN_SHAPES))
            ->addOption('skill-tags', null, InputOption::VALUE_REQUIRED, 'Comma-separated boost-skills tags for .config/boost.php: ' . implode(',', SkillTagsQuestion::TAGS))
            ->addOption('commit', null, InputOption::VALUE_NONE, 'Make an initial commit after scaffolding.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('with-security-advisories') === true) {
            $io->warning('--with-security-advisories is deprecated and does nothing: repo-init no longer ships that opt-in. It will be removed in the next major. For a laravel-project that uses spatie/laravel-health, pass --with-health-checks.');
        }

        try {
            $state = $this->buildStateFromFlags($input);

            $wizard = new Wizard();
            $wizard->run($io, $state);

            PhpVersionPolicy::assertAllowed($state->phpVersion ?? '', $state->category);
            $this->assertTestFrameworkSupported($state);
            $this->assertLaravelRangeSupported($state);
            $this->assertHostPhpSupported($state);

            // Final validation.
            $missing = $this->missingFields($state);
            if ($missing !== []) {
                $io->error('Missing required fields: ' . implode(', ', $missing));

                return 64;
            }

            $this->fillAuthor($state);

            $resolver = new TargetDirResolver();
            $name = $input->getArgument('name');
            $explicitName = is_string($name) ? $name : null;

            // If user gave only --vendor and no positional name, use package as dir name.
            $dirName = $explicitName ?? $state->package;
            $targetDir = $resolver->resolve($dirName, (string) getcwd());
            $state->targetDir = $targetDir;

            $this->confirmPlan($io, $state, $targetDir);

            $locator = new RepoInitLocator();
            $repoInitDir = $locator->locate();

            $stubReader = new StubReader($repoInitDir);
            $deps = new PerCategoryDeps($repoInitDir . '/references/per-category-deps.yml');
            $composer = new ComposerRunner($io, new ComposerFailureSurfacer($io));

            $scaffolder = new Scaffolder(
                new PackageScaffolder($io, $stubReader, $deps, $composer),
                new LaravelProjectScaffolder($io, $stubReader, $deps, $composer),
            );

            $result = $scaffolder->scaffold($state, $targetDir);

            $git = new GitInitializer($io);
            $git->init($targetDir);
            if ($state->commit) {
                $git->initialCommit($targetDir);
            }

            $io->success("Scaffold complete at {$targetDir}");
            $io->writeln('Summary: ' . json_encode($result, JSON_THROW_ON_ERROR));

            $handoff = new HandoffPromptBuilder()->build($state, $targetDir);
            $io->newLine();
            $io->writeln('Next: copy-paste this to Claude:');
            $io->writeln('');
            $io->writeln('----- 8< -----');
            $io->writeln($handoff);
            $io->writeln('----- 8< -----');

            return Command::SUCCESS;
        } catch (RuntimeException|InvalidArgumentException $exception) {
            // RuntimeException: scaffolding/locate failures. InvalidArgumentException: flag validation.
            $io->error($exception->getMessage());

            return 65;
        }
    }

    private function buildStateFromFlags(InputInterface $input): WizardState
    {
        $state = new WizardState();

        $state->interactive = $input->getOption('no-interaction') !== true;

        $this->applyType($input, $state);
        $this->applyVendorAndPackage($input, $state);

        $state->description = $this->nonEmptyStringOption($input, 'description');
        $state->phpVersion = $this->nonEmptyStringOption($input, 'php');
        $state->laravelVersions = $this->nonEmptyStringOption($input, 'laravel');

        // Fail fast on an unsupported --php when the category is already known;
        // execute() re-checks after the wizard for an interactively-picked category.
        if ($state->phpVersion !== null && $state->category !== null) {
            PhpVersionPolicy::assertAllowed($state->phpVersion, $state->category);
        }

        $this->applyTestFrameworkFlag($input, $state);
        $this->applyVariantFlag($input, $state);

        $hihahoRules = $input->getOption('with-hihaho-rules');
        $state->withHihahoRules = is_bool($hihahoRules) ? $hihahoRules : null;
        $state->withHealthChecks = $input->getOption('with-health-checks') === true;
        $state->laravelAware = $input->getOption('laravel-aware') === true;
        $state->commit = $input->getOption('commit') === true;

        $this->applyPluginShapeFlag($input, $state);
        $this->applySkillTagsFlag($input, $state);

        return $state;
    }

    private function nonEmptyStringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function applyVariantFlag(InputInterface $input, WizardState $state): void
    {
        $variant = $input->getOption('variant');
        if (! is_string($variant) || $variant === '') {
            return;
        }

        if (! in_array($variant, VariantQuestion::VARIANTS, true)) {
            throw new InvalidArgumentException(
                '--variant must be one of: ' . implode(', ', VariantQuestion::VARIANTS) . ", got '{$variant}'",
            );
        }

        $state->variant = $variant;
    }

    private function applyPluginShapeFlag(InputInterface $input, WizardState $state): void
    {
        $shape = $input->getOption('plugin-shape');
        if (! is_string($shape) || $shape === '') {
            return;
        }

        if (! in_array($shape, self::PLUGIN_SHAPES, true)) {
            throw new InvalidArgumentException(
                '--plugin-shape must be one of: ' . implode(', ', self::PLUGIN_SHAPES) . ", got '{$shape}'",
            );
        }

        $state->pluginShape = $shape;
    }

    private function applySkillTagsFlag(InputInterface $input, WizardState $state): void
    {
        $raw = $input->getOption('skill-tags');
        if (! is_string($raw)) {
            return;
        }

        $tags = array_values(array_filter(
            array_map(trim(...), explode(',', $raw)),
            static fn (string $tag): bool => $tag !== '',
        ));

        foreach ($tags as $tag) {
            if (! in_array($tag, SkillTagsQuestion::TAGS, true)) {
                throw new InvalidArgumentException(
                    '--skill-tags values must be from: ' . implode(', ', SkillTagsQuestion::TAGS) . ", got '{$tag}'",
                );
            }
        }

        $state->skillTags = $tags;
    }

    private function applyType(InputInterface $input, WizardState $state): void
    {
        $type = $input->getOption('type');
        if (is_string($type) && $type !== '') {
            if (! in_array($type, self::CATEGORIES, true)) {
                throw new RuntimeException('Invalid --type. Allowed: ' . implode(', ', self::CATEGORIES));
            }

            $state->category = $type;
        }
    }

    private function applyVendorAndPackage(InputInterface $input, WizardState $state): void
    {
        $vendor = $input->getOption('vendor');
        $name = $input->getArgument('name');
        $nameStr = is_string($name) ? $name : null;

        $isComposerName = $nameStr !== null
            && preg_match('#^[a-z0-9][a-z0-9._-]*/[a-z0-9][a-z0-9._-]*$#', $nameStr) === 1;

        if (is_string($vendor) && $vendor !== '') {
            $state->vendor = $vendor;
            if ($nameStr !== null) {
                $state->package = $isComposerName ? explode('/', $nameStr, 2)[1] : basename($nameStr);
            }
        } elseif ($isComposerName && $nameStr !== null) {
            [$state->vendor, $state->package] = explode('/', $nameStr, 2);
        } elseif ($nameStr !== null) {
            $state->package = basename($nameStr);
        }
    }

    private function applyTestFrameworkFlag(InputInterface $input, WizardState $state): void
    {
        $tf = $input->getOption('test-framework');
        if (is_string($tf) && $tf !== '') {
            if (! in_array($tf, ['pest', 'phpunit'], true)) {
                throw new InvalidArgumentException("--test-framework must be 'pest' or 'phpunit', got '{$tf}'");
            }

            $state->testFramework = $tf;
        }

        $this->assertTestFrameworkSupported($state);
    }

    /**
     * Laravel-project + pest needs `pest --init` to migrate from PHPUnit
     * tests, which the scaffolder doesn't run yet. Reject the combo with a
     * clear message instead of half-applying it.
     */
    /**
     * Pest 5 cannot install next to testbench 10, so a Pest laravel-package
     * tests on Laravel 13 (testbench 11) and its range must include it.
     */
    private function assertLaravelRangeSupported(WizardState $state): void
    {
        if ($state->category !== 'laravel-package' || $state->testFramework !== 'pest') {
            return;
        }

        // Some `||` alternative must start at major 13 (`^13.0`, `~13.1`, `13.*`).
        if (preg_match('/(?:^|\|)\s*[\^~]?13(?:\.|\s*(?:\||$))/', $state->laravelVersions ?? '') !== 1) {
            throw new InvalidArgumentException(
                "--laravel={$state->laravelVersions} cannot be tested with Pest 5, which needs orchestra/testbench 11 (Laravel 13). Include ^13.0 in --laravel or use --test-framework=phpunit.",
            );
        }
    }

    /**
     * composer and `laravel new` run on this PHP, so it must meet the scaffold's floor.
     */
    private function assertHostPhpSupported(WizardState $state): void
    {
        $phpVersion = $state->phpVersion ?? '';
        if (version_compare(PHP_VERSION, $phpVersion, '>=')) {
            return;
        }

        throw new InvalidArgumentException(
            "--php={$phpVersion} needs PHP {$phpVersion} or newer to run composer, but this is PHP " . PHP_VERSION . '. Run repo-new with a newer PHP.',
        );
    }

    private function assertTestFrameworkSupported(WizardState $state): void
    {
        if ($state->category === 'laravel-project' && $state->testFramework === 'pest') {
            throw new InvalidArgumentException(
                "laravel-project does not yet support --test-framework=pest (would require running `pest --init` to migrate Laravel's PHPUnit tests). Use phpunit or migrate manually after scaffold.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function missingFields(WizardState $state): array
    {
        $missing = [];

        if ($state->category === null) {
            $missing[] = 'type';
        }

        if ($state->category !== 'laravel-project' && $state->vendor === null) {
            $missing[] = 'vendor';
        }

        if ($state->category !== 'laravel-project' && $state->package === null) {
            $missing[] = 'name (package)';
        }

        if ($state->description === null || $state->description === '') {
            $missing[] = 'description';
        }

        if ($state->phpVersion === null) {
            $missing[] = 'php';
        }

        return $missing;
    }

    private function fillAuthor(WizardState $state): void
    {
        if ($state->authorName === null) {
            $proc = new Process(['git', 'config', '--global', 'user.name']);
            $proc->run();
            $name = trim($proc->getOutput());
            $state->authorName = $name !== '' ? $name : 'Sander Muller';
        }

        if ($state->authorEmail === null) {
            $proc = new Process(['git', 'config', '--global', 'user.email']);
            $proc->run();
            $email = trim($proc->getOutput());
            $state->authorEmail = $email !== '' ? $email : 'github@scode.nl';
        }
    }

    private function confirmPlan(SymfonyStyle $io, WizardState $state, string $targetDir): void
    {
        $io->section('Plan');
        $io->definitionList(
            ['Category' => $state->category ?? ''],
            ['Plugin shape' => $state->pluginShape ?? '—'],
            ['Variant' => $state->variant ?? '—'],
            ['Composer name' => $state->composerName() ?? ''],
            ['Description' => $state->description ?? ''],
            ['PHP' => $state->phpVersion ?? ''],
            ['Laravel' => $state->laravelVersions ?? '—'],
            ['Test framework' => $state->category === 'skill-bundle' ? '—' : ($state->testFramework ?? '')],
            ['Skill tags' => $state->skillTags === null || $state->skillTags === [] ? '—' : implode(', ', $state->skillTags)],
            ['Author' => ($state->authorName ?? '') . ' <' . ($state->authorEmail ?? '') . '>'],
            ['Target dir' => $targetDir],
        );

        if (! $state->interactive) {
            return;
        }

        if (! $io->confirm('Proceed?', true)) {
            throw new RuntimeException('Aborted by user.');
        }
    }
}
