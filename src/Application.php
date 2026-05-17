<?php declare(strict_types=1);

namespace SanderMuller\RepoNew;

use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public const string VERSION = '0.1.0-dev';

    public function __construct()
    {
        parent::__construct('repo-new', self::VERSION);

        $this->addCommand(new NewCommand());
    }
}
