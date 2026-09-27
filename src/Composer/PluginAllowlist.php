<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Composer;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Allow-lists Composer plugins in a target composer.json before the composer
 * call that installs them — otherwise composer aborts with "contains a
 * Composer plugin which is blocked by your allow-plugins config".
 *
 * An existing `true` is left alone. An existing `false` is an explicit denial
 * (bootstrap-laravel-project.md step 5): allow() throws rather than flip it.
 */
final readonly class PluginAllowlist
{
    public function __construct(private string $composerBinary = 'composer') {}

    /**
     * @param  list<string>  $plugins
     */
    public function allow(string $targetDir, array $plugins): void
    {
        $current = ComposerJson::map(ComposerJson::map(ComposerJson::read($targetDir . '/composer.json'), 'config'), 'allow-plugins');

        foreach ($plugins as $plugin) {
            if (($current[$plugin] ?? null) === true) {
                continue;
            }

            if (($current[$plugin] ?? null) === false) {
                throw new RuntimeException(
                    "composer.json denies the Composer plugin {$plugin} (config.allow-plugins.\"{$plugin}\" is false). "
                    . 'repo-new does not override an explicit denial: set it to true or remove the entry, then re-run.',
                );
            }

            $process = new Process(
                [$this->composerBinary, 'config', '--no-plugins', "allow-plugins.{$plugin}", 'true'],
                $targetDir,
                null,
                null,
                60.0,
            );
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    "composer config allow-plugins.{$plugin} failed (exit {$process->getExitCode()}): " . trim($process->getErrorOutput()),
                );
            }
        }
    }
}
