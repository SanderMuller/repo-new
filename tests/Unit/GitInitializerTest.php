<?php declare(strict_types=1);

use SanderMuller\RepoNew\Git\GitInitializer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-git-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);
    $this->git = new GitInitializer(new SymfonyStyle(new ArrayInput([]), new BufferedOutput()));
});

afterEach(function (): void {
    removeDirectory($this->tmp);
});

it('reports no commits for a fresh repository, and a commit after one', function (): void {
    $this->git->init($this->tmp);

    expect($this->git->hasCommits($this->tmp))->toBeFalse();

    file_put_contents($this->tmp . '/README.md', "demo\n");
    new Process(['git', 'add', '-A'], $this->tmp)->mustRun();
    new Process(
        ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', '-c', 'commit.gpgsign=false', 'commit', '-qm', 'init'],
        $this->tmp,
    )->mustRun();

    expect($this->git->hasCommits($this->tmp))->toBeTrue();
});
