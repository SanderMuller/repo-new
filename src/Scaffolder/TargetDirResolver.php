<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;

/**
 * Implements the target-dir rule per spec §3:
 *  - Positional `name`: `mkdir <name>` (relative to cwd, unless absolute);
 *    refuse if `<name>` already exists as a non-empty dir.
 *  - No `name`: cwd is the target. Verify cwd is empty modulo `.git/`.
 *
 * Returns the resolved absolute target directory path. Does NOT chdir.
 */
final class TargetDirResolver
{
    public function resolve(?string $name, string $cwd): string
    {
        if ($name !== null && $name !== '') {
            $target = $this->isAbsolute($name) ? $name : rtrim($cwd, '/') . '/' . $name;

            if (is_dir($target)) {
                // Allow empty directory; refuse if non-empty.
                if (! $this->isEmpty($target)) {
                    throw new RuntimeException("Target dir already exists and is not empty: {$target}");
                }

                $real = realpath($target);

                return rtrim($real !== false ? $real : $target, '/');
            }

            if (! mkdir($target, 0755, true) && ! is_dir($target)) {
                throw new RuntimeException("Failed to mkdir target dir: {$target}");
            }

            $real = realpath($target);

            return rtrim($real !== false ? $real : $target, '/');
        }

        // No name → cwd, must be empty modulo .git/.
        if (! $this->isEmptyModuloGit($cwd)) {
            throw new RuntimeException(
                'No name given and current directory is not empty (modulo .git/). Pass a name or cd into an empty dir.',
            );
        }

        $real = realpath($cwd);

        return rtrim($real !== false ? $real : $cwd, '/');
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    private function isEmpty(string $dir): bool
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        return array_values(array_diff($entries, ['.', '..'])) === [];
    }

    private function isEmptyModuloGit(string $dir): bool
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        $remaining = array_values(array_diff($entries, ['.', '..', '.git']));

        return $remaining === [];
    }
}
