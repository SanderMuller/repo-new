<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;

/**
 * The repo-init workflow stubs pin PHP 8.4 (the package floor). When the
 * scaffold floors higher (`--php=8.5`, and every laravel-project), an 8.4
 * cell cannot install against `require.php: ^8.5` — so every pinned `8.4`
 * in .github/workflows/*.yml moves to the chosen version.
 */
final class WorkflowPhpVersion
{
    private const string STUB_FLOOR = '8.4';

    public function apply(string $targetDir, string $phpVersion): void
    {
        if ($phpVersion === self::STUB_FLOOR) {
            return;
        }

        $workflows = glob($targetDir . '/.github/workflows/*.yml');

        foreach ($workflows === false ? [] : $workflows as $path) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("Failed to read {$path}");
            }

            $updated = (string) preg_replace(
                "#\\b(php(?:-version)?:\\s*)'" . preg_quote(self::STUB_FLOOR, '#') . "'#",
                "\${1}'{$phpVersion}'",
                $contents,
            );

            if ($updated !== $contents && file_put_contents($path, $updated) === false) {
                throw new RuntimeException("Failed to write {$path}");
            }
        }
    }
}
