<?php declare(strict_types=1);

use SanderMuller\RepoNew\Composer\PluginAllowlist;

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-allowlist-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);

    // Stand-in composer binary that always fails.
    $this->failingComposer = $this->tmp . '/composer-fails';
    file_put_contents($this->failingComposer, "#!/bin/sh\necho 'nope' >&2\nexit 7\n");
    chmod($this->failingComposer, 0755);
});

afterEach(function (): void {
    removeDirectory($this->tmp);
});

it('fails when composer config fails', function (): void {
    file_put_contents($this->tmp . '/composer.json', '{"config": {}}');

    new PluginAllowlist($this->failingComposer)->allow($this->tmp, ['phpstan/extension-installer']);
})->throws(RuntimeException::class, 'composer config allow-plugins.phpstan/extension-installer failed (exit 7): nope');

it('runs no composer command for a plugin already allowed', function (): void {
    file_put_contents($this->tmp . '/composer.json', '{"config": {"allow-plugins": {"phpstan/extension-installer": true}}}');

    new PluginAllowlist($this->failingComposer)->allow($this->tmp, ['phpstan/extension-installer']);

    expect(composerJsonOf($this->tmp))->toHaveKey('config.allow-plugins.phpstan/extension-installer', true);
});
