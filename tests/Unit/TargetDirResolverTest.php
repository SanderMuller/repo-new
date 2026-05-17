<?php declare(strict_types=1);

use SanderMuller\RepoNew\Scaffolder\TargetDirResolver;

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-test-' . bin2hex(random_bytes(4));
    mkdir($this->tmp);
});

afterEach(function (): void {
    if (is_dir($this->tmp)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
    }
});

it('creates subdir when name is given relative to cwd', function (): void {
    $resolver = new TargetDirResolver();
    $resolved = $resolver->resolve('foo', $this->tmp);

    expect(is_dir($this->tmp . '/foo'))->toBeTrue();
    expect($resolved)->toEndWith('/foo');
});

it('returns cwd when no name and cwd is empty', function (): void {
    $resolver = new TargetDirResolver();
    $resolved = $resolver->resolve(null, $this->tmp);

    expect(realpath($resolved))->toBe(realpath($this->tmp));
});

it('returns cwd when no name and cwd contains only .git', function (): void {
    mkdir($this->tmp . '/.git');
    $resolver = new TargetDirResolver();
    $resolved = $resolver->resolve(null, $this->tmp);

    expect(realpath($resolved))->toBe(realpath($this->tmp));
});

it('throws when no name and cwd is non-empty', function (): void {
    file_put_contents($this->tmp . '/existing-file', 'data');
    $resolver = new TargetDirResolver();
    $resolver->resolve(null, $this->tmp);
})->throws(RuntimeException::class, 'not empty');

it('throws when target name already exists and non-empty', function (): void {
    mkdir($this->tmp . '/foo');
    file_put_contents($this->tmp . '/foo/x', 'x');
    $resolver = new TargetDirResolver();
    $resolver->resolve('foo', $this->tmp);
})->throws(RuntimeException::class, 'already exists');

it('uses absolute path when name is absolute', function (): void {
    $abs = $this->tmp . '/absolute-target';
    $resolver = new TargetDirResolver();
    $resolved = $resolver->resolve($abs, '/some/other/cwd');

    expect(realpath($resolved))->toBe(realpath($abs));
});
