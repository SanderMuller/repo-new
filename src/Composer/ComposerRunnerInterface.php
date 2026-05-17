<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Composer;

interface ComposerRunnerInterface
{
    public function install(string $cwd): void;

    /**
     * @param  list<string>  $packages
     */
    public function require(string $cwd, array $packages, bool $dev = false): void;

    /**
     * @param  list<string>  $packages
     */
    public function remove(string $cwd, array $packages, bool $noUpdate = false): void;
}
