<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\Composer\PluginAllowlist;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Wizard\PhpVersionPolicy;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Scaffolds a laravel-project: shells out to `laravel new` and overlays the
 * stubs/laravel-project/ additions on top.
 */
final readonly class LaravelProjectScaffolder
{
    private const string README_APPEND = 'README.append.md';

    /** Stub-relative paths `laravel new --boost` already writes. */
    private const array SKIP_IF_EXISTS = ['boost.json'];

    /**
     * The shared .mcp.json runs `vendor/bin/testbench boost:mcp`, which a
     * project doesn't have; laravel/boost writes the project's own.
     */
    private const array NEVER_COPY = ['.mcp.json'];

    public function __construct(
        private SymfonyStyle $io,
        private StubReader $stubReader,
        private PerCategoryDeps $deps,
        private ComposerRunnerInterface $composer,
    ) {}

    /**
     * @return array{stubsWritten: int, requireInstalled: int, requireDevInstalled: int}
     */
    public function scaffold(WizardState $state, string $targetDir): array
    {
        $laravelBin = new ExecutableFinder()->find('laravel');
        if ($laravelBin === null) {
            throw new RuntimeException(
                'laravel binary not found on PATH. Install via `composer global require laravel/installer` first.',
            );
        }

        // Run `laravel new <name>` from the parent of the target dir. The CLI
        // creates the dir for us, so it must NOT exist yet (or be empty).
        $parent = dirname($targetDir);
        $name = basename($targetDir);

        // If target dir already exists and is empty, laravel new may refuse;
        // use `--force` to overlay or pre-clean. For simplicity, the resolver
        // only mkdir'd the directory; remove it so `laravel new` can create it.
        if (is_dir($targetDir) && $this->isEmpty($targetDir)) {
            @rmdir($targetDir);
        }

        $process = new Process([$laravelBin, 'new', $name, '--boost', '--git', '--no-interaction'], $parent, null, null, 600.0);
        $this->io->writeln("<info>→ laravel new {$name}</info>");
        $process->run(function (string $type, string $buffer): void {
            $this->io->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException("laravel new {$name} failed (exit {$process->getExitCode()})");
        }

        return $this->overlay($state, $targetDir);
    }

    /**
     * Everything after `laravel new`.
     *
     * @return array{stubsWritten: int, requireInstalled: int, requireDevInstalled: int}
     */
    public function overlay(WizardState $state, string $targetDir): array
    {
        // Rewrite composer.json with user-supplied identity (name, description,
        // keywords, authors, homepage) and the PHP floor. Laravel installer ships
        // "laravel/laravel" + skeleton blurb which would be wrong for a project.
        $this->rewriteComposerJson($targetDir, $state);

        $substituter = new PlaceholderSubstituter($state);
        $written = $this->overlayStubs($targetDir, $substituter);

        if ($state->withHihahoRules === true) {
            $this->wireHihahoRules($targetDir);
        }

        new WorkflowPhpVersion()->apply($targetDir, $state->phpVersion ?? PhpVersionPolicy::defaultFor('laravel-project'));

        $optInFlags = [
            'with-hihaho-rules' => $state->withHihahoRules === true,
            'with-health-checks' => $state->withHealthChecks,
        ];
        $depList = $this->deps->forCategory('laravel-project', $state->testFramework ?? 'phpunit', $optInFlags);
        $require = array_map($substituter->substitute(...), $depList->require);
        $requireDev = array_map($substituter->substitute(...), $depList->requireDev);

        if ($require !== []) {
            $this->composer->require($targetDir, $require, dev: false);
        }

        if ($requireDev !== []) {
            // Pre-config allow-plugins for plugins our deps will pull in.
            // Laravel ships some (pestphp/pest-plugin, php-http/discovery)
            // but not phpstan/extension-installer which our shared dep list
            // requires. Without this, composer aborts with "contains a
            // Composer plugin which is blocked by your allow-plugins config".
            new PluginAllowlist()->allow($targetDir, [
                'phpstan/extension-installer',
            ]);

            // For packages that Laravel 13+ ships in `require` but we want in
            // `require-dev` (laravel/tinker is the canonical case), explicitly
            // remove from require first so `composer require --dev` doesn't
            // just leave them in require. Composer's auto-move warning isn't
            // reliable across versions and can leave packages in the wrong
            // scope when the require-dev call has other resolution conflicts.
            $alreadyInRequire = $this->listPackagesInRequire($targetDir);
            $toMove = array_values(array_intersect($requireDev, $alreadyInRequire));

            if ($toMove !== []) {
                $this->io->writeln('<comment>→ moving to require-dev: ' . implode(', ', $toMove) . '</comment>');
                $this->composer->remove($targetDir, $toMove, noUpdate: true);
            }

            // Now install everything as --dev. tinker (or any moved package)
            // re-enters via require-dev cleanly.
            $this->composer->require($targetDir, $requireDev, dev: true);
        }

        $this->registerSecurityMiddleware($targetDir);

        // No boost sync here: laravel-project uses laravel/boost (wired by
        // `laravel new --boost`), not boost-core — there is no vendor/bin/boost.

        return ['stubsWritten' => $written, 'requireInstalled' => count($require), 'requireDevInstalled' => count($requireDev)];
    }

    /**
     * Appends SecurityHeaders + HSTS to the global middleware stack in
     * bootstrap/app.php (laravel-security-canon.md: `append()`, not `web()`),
     * so the headers land on API and webhook responses too.
     */
    private function registerSecurityMiddleware(string $targetDir): void
    {
        $path = $targetDir . '/bootstrap/app.php';
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            $this->warnMiddlewareNotRegistered('bootstrap/app.php was not found');

            return;
        }

        if (str_contains($contents, 'SecurityHeaders::class') && str_contains($contents, 'StrictTransportSecurity::class')) {
            return;
        }

        $updated = preg_replace_callback(
            '#(->withMiddleware\(function \(Middleware \$middleware\)(?:: void)? \{)\R([ \t]*)//\R#',
            static fn (array $match): string => $match[1] . "\n"
                . $match[2] . '$middleware->append([' . "\n"
                . $match[2] . '    \App\Http\Middleware\SecurityHeaders::class,' . "\n"
                . $match[2] . '    \Zae\StrictTransportSecurity\Middleware\L5\StrictTransportSecurity::class,' . "\n"
                . $match[2] . ']);' . "\n",
            $contents,
            1,
            $count,
        );

        if ($updated === null || $count !== 1) {
            $this->warnMiddlewareNotRegistered('bootstrap/app.php has no empty `->withMiddleware(function (Middleware $middleware) { // })` block');

            return;
        }

        if (file_put_contents($path, $updated) === false) {
            throw new RuntimeException("Failed to write {$path}");
        }
    }

    /**
     * The stub phpstan.neon.dist and rector.php carry commented hints for the
     * hihaho rule packs (bootstrap-laravel-project.md step 7); turn them into
     * config. Warns when a hint is missing rather than guessing a location.
     */
    private function wireHihahoRules(string $targetDir): void
    {
        $edits = [
            'phpstan.neon.dist' => [
                "    # When --with-hihaho-rules is opted in, also include:\n    # - vendor/hihaho/phpstan-rules/extension.neon\n",
                "    - vendor/hihaho/phpstan-rules/extension.neon\n",
            ],
            'rector.php' => [
                "            // With --with-hihaho-rules, add HihahoSetList::ALL here. ALL does not\n            // cover every rule in the package — see references/rector-config.md.\n",
                "            \\Hihaho\\RectorRules\\Set\\HihahoSetList::ALL,\n",
            ],
        ];

        foreach ($edits as $file => [$hint, $config]) {
            $path = $targetDir . '/' . $file;
            $contents = is_file($path) ? file_get_contents($path) : false;

            if ($contents === false || ! str_contains($contents, $hint)) {
                $this->io->warning("hihaho rules NOT wired into {$file}: its --with-hihaho-rules hint was not found. Wire it by hand (repo-init references/rector-config.md, phpstan-config.md).");

                continue;
            }

            if (file_put_contents($path, str_replace($hint, $config, $contents)) === false) {
                throw new RuntimeException("Failed to write {$path}");
            }
        }
    }

    /**
     * Not fatal: `laravel new` and every composer step already ran, so failing
     * here would strand a scaffold that cannot be re-run.
     */
    private function warnMiddlewareNotRegistered(string $reason): void
    {
        $this->io->warning(
            "Security middleware NOT registered: {$reason}. Append SecurityHeaders::class and "
            . 'Zae\\StrictTransportSecurity\\Middleware\\L5\\StrictTransportSecurity::class via `$middleware->append([...])` '
            . 'in bootstrap/app.php by hand (repo-init references/laravel-security-canon.md).',
        );
    }

    /**
     * Return the names of packages currently in target's composer.json `require` (non-dev).
     *
     * @return list<string>
     */
    private function listPackagesInRequire(string $targetDir): array
    {
        $composerJson = $targetDir . '/composer.json';
        if (! is_file($composerJson)) {
            return [];
        }

        $raw = file_get_contents($composerJson);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['require']) || ! is_array($decoded['require'])) {
            return [];
        }

        return array_keys($decoded['require']);
    }

    /**
     * Overlay shared/ baseline (CI workflows, pint, editorconfig, etc.) but
     * SKIP files that would clobber Laravel-shipped project defaults. Then
     * overlay laravel-project/ additions on top.
     */
    private function overlayStubs(string $targetDir, PlaceholderSubstituter $substituter): int
    {
        // Which stubs/shared/ files laravel-project skips is repo-init's
        // `shared-stub-skip` denylist (per-category-deps.yml) — files that
        // would clobber Laravel-shipped project defaults.
        $skipper = new SharedStubSkipper([...$this->deps->sharedStubSkipFor('laravel-project'), ...self::NEVER_COPY]);

        $written = 0;
        foreach (['shared', 'laravel-project'] as $stubDir) {
            foreach ($this->stubReader->read($stubDir) as $stub) {
                if ($stubDir === 'shared' && $skipper->shouldSkip($stub['relative'])) {
                    continue;
                }

                // Appended to Laravel's README.md, never written as a file.
                if ($stubDir === 'laravel-project' && $stub['relative'] === self::README_APPEND) {
                    $this->appendReadme($stub['source'], $targetDir);

                    continue;
                }

                if (in_array($stub['relative'], self::SKIP_IF_EXISTS, true) && file_exists($targetDir . '/' . $stub['relative'])) {
                    continue;
                }

                $this->copyStub($stub, $targetDir, $substituter);
                ++$written;
            }
        }

        return $written;
    }

    /**
     * Idempotent: skips when README.md already contains the stub's first line.
     */
    private function appendReadme(string $source, string $targetDir): void
    {
        $append = file_get_contents($source);
        if ($append === false) {
            throw new RuntimeException("Failed to read {$source}");
        }

        $readmePath = $targetDir . '/README.md';
        $readme = is_file($readmePath) ? (string) file_get_contents($readmePath) : '';
        $firstLine = strtok($append, "\n");
        if ($firstLine !== false && str_contains($readme, trim($firstLine))) {
            return;
        }

        $separator = $readme === '' ? '' : rtrim($readme, "\n") . "\n\n";
        if (file_put_contents($readmePath, $separator . $append) === false) {
            throw new RuntimeException("Failed to write {$readmePath}");
        }
    }

    /**
     * @param  array{source: string, relative: string}  $stub
     */
    private function copyStub(array $stub, string $targetDir, PlaceholderSubstituter $substituter): void
    {
        $relativeSubstituted = $this->dotPrefixRename($substituter->substitute($stub['relative']));
        $destination = $targetDir . '/' . $relativeSubstituted;

        $destinationDir = dirname($destination);
        if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
            throw new RuntimeException("Failed to mkdir {$destinationDir}");
        }

        $contents = file_get_contents($stub['source']);
        if ($contents === false) {
            throw new RuntimeException("Failed to read {$stub['source']}");
        }

        file_put_contents($destination, $substituter->substitute($contents));
    }

    /**
     * Rename leading `_` to `.` in each path segment. See PackageScaffolder
     * for the rationale (stub `.gitattributes` would otherwise strip itself
     * + many sibling files from the published source tarball).
     */
    private function dotPrefixRename(string $relative): string
    {
        $segments = array_map(
            static fn (string $s): string => str_starts_with($s, '_') ? '.' . substr($s, 1) : $s,
            explode('/', $relative),
        );

        return implode('/', $segments);
    }

    /**
     * Rewrite composer.json identity fields from Laravel installer defaults
     * to user-supplied vendor/package/description/authors, and raise the PHP floor.
     */
    private function rewriteComposerJson(string $targetDir, WizardState $state): void
    {
        $path = $targetDir . '/composer.json';
        if (! is_file($path)) {
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return;
        }

        $composerName = $state->composerName();
        if ($composerName !== null) {
            $decoded['name'] = $composerName;
        }

        if ($state->description !== null && $state->description !== '') {
            $decoded['description'] = $state->description;
        }

        if ($state->vendor !== null && $state->package !== null) {
            $decoded['keywords'] = [$state->vendor, $state->package, 'laravel'];
            $decoded['homepage'] = "https://github.com/{$state->vendor}/{$state->package}";
        }

        // `laravel new` writes `"php": "^8.3"`; raise it to the laravel-project
        // floor before any composer command so one resolution sees the final floor.
        $decoded['require'] = is_array($decoded['require'] ?? null) ? $decoded['require'] : [];
        $decoded['require']['php'] = '^' . ($state->phpVersion ?? PhpVersionPolicy::defaultFor('laravel-project'));

        if ($state->authorName !== null && $state->authorEmail !== null) {
            $decoded['authors'] = [[
                'name' => $state->authorName,
                'email' => $state->authorEmail,
                'role' => 'Developer',
            ]];
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        file_put_contents($path, $encoded . "\n");
    }

    private function isEmpty(string $dir): bool
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        return array_values(array_diff($entries, ['.', '..'])) === [];
    }
}
