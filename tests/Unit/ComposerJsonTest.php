<?php declare(strict_types=1);

use SanderMuller\RepoNew\Composer\ComposerJson;

it('keeps empty composer.json maps as {} across a read/write round-trip', function (): void {
    $path = sys_get_temp_dir() . '/repo-new-composer-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($path, '{"require": {}, "keywords": [], "config": {"allow-plugins": {"a/b": true}}}');

    $json = ComposerJson::read($path);
    $config = ComposerJson::map($json, 'config');
    $config['allow-plugins'] = [];
    $json['config'] = $config;
    ComposerJson::write($path, $json);

    $written = json_decode(readFileContents($path), false, 512, JSON_THROW_ON_ERROR);
    unlink($path);

    expect($written)->toBeInstanceOf(stdClass::class)
        ->and(json_encode($written, JSON_UNESCAPED_SLASHES))
        ->toBe('{"require":{},"keywords":[],"config":{"allow-plugins":{}}}');
});
