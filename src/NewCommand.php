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
    ];

    private const array PLUGIN_SHAPES = ['command-provider', 'event-subscriber', 'both', 'none'];

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Target dir / package name (kebab-case).')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Category: ' . implode(', ', self::CATEGORIES))
            ->addOption('vendor', null, InputOption::VALUE_REQUIRED, 'Composer vendor.')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'One-line description.')
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'PHP version: 8.3|8.4|8.5')
            ->addOption('laravel', null, InputOption::VALUE_REQUIRED, 'Laravel constraint (laravel-package only).')
            ->addOption('test-framework', null, InputOption::VALUE_REQUIRED, 'pest|phpunit')
            ->addOption('with-hihaho-rules', null, InputOption::VALUE_NONE, 'Opt-in for laravel-project.')
            ->addOption('with-security-advisories', null, InputOption::VALUE_NONE, 'Opt-in for laravel-project.')
            ->addOption('laravel-aware', null, InputOption::VALUE_NONE, 'Opt-in for phpstan/rector-extension.')
            ->addOption('plugin-shape', null, InputOption::VALUE_REQUIRED, 'composer-plugin shape: ' . implode('|', self::PLUGIN_SHAPES))
            ->addOption('commit', null, InputOption::VALUE_NONE, 'Make an initial commit after scaffolding.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $state = $this->buildStateFromFlags($input);

            $wizard = new Wizard();
            $wizard->run($io, $state);

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

            $handoff = (new HandoffPromptBuilder())->build($state, $targetDir);
            $io->newLine();
            $io->writeln('Next: copy-paste this to Claude:');
            $io->writeln('');
            $io->writeln('----- 8< -----');
            $io->writeln($handoff);
            $io->writeln('----- 8< -----');

            return Command::SUCCESS;
        } catch (RuntimeException $runtimeException) {
            $io->error($runtimeException->getMessage());

            return 65;
        }
    }

    private function buildStateFromFlags(InputInterface $input): WizardState
    {
        $state = new WizardState();

        $state->interactive = $input->getOption('no-interaction') !== true;

        $this->applyType($input, $state);
        $this->applyVendorAndPackage($input, $state);

        foreach (['description' => 'description', 'php' => 'phpVersion', 'laravel' => 'laravelVersions'] as $opt => $field) {
            $val = $input->getOption($opt);
            if (is_string($val) && $val !== '') {
                $state->{$field} = $val;
            }
        }

        $this->applyTestFrameworkFlag($input, $state);

        $state->withHihahoRules = $input->getOption('with-hihaho-rules') === true;
        $state->withSecurityAdvisories = $input->getOption('with-security-advisories') === true;
        $state->laravelAware = $input->getOption('laravel-aware') === true;
        $state->commit = $input->getOption('commit') === true;

        $this->applyPluginShapeFlag($input, $state);

        return $state;
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

        // Laravel-project + pest needs `pest --init` to migrate from PHPUnit
        // tests, which the scaffolder doesn't run yet. Reject the combo with a
        // clear message instead of half-applying it.
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
            ['Composer name' => $state->composerName() ?? ''],
            ['Description' => $state->description ?? ''],
            ['PHP' => $state->phpVersion ?? ''],
            ['Laravel' => $state->laravelVersions ?? '—'],
            ['Test framework' => $state->testFramework ?? ''],
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
