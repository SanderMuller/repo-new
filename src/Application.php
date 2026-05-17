<?php declare(strict_types=1);

namespace SanderMuller\RepoNew;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public const string PACKAGE = 'sandermuller/repo-new';

    public function __construct()
    {
        parent::__construct('repo-new', $this->resolveVersion());

        $this->addCommand(new NewCommand());
    }

    private function resolveVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'dev';
        } catch (OutOfBoundsException) {
            return 'dev';
        }
    }
}
