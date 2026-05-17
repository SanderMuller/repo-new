<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\RepoInit;

use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Walks a stubs/<category>/ tree and yields (absolute-source, relative-target) tuples.
 */
final class StubReader
{
    public function __construct(private readonly string $repoInitDir) {}

    /**
     * @return Generator<array{source: string, relative: string}>
     */
    public function read(string $stubDir): Generator
    {
        $base = $this->repoInitDir . '/stubs/' . $stubDir;
        if (! is_dir($base)) {
            throw new RuntimeException("Stub dir not found: {$base}");
        }

        $base = rtrim($base, '/');
        $baseLen = strlen($base) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $abs = $file->getPathname();
            $relative = substr($abs, $baseLen);

            yield ['source' => $abs, 'relative' => $relative];
        }
    }
}
