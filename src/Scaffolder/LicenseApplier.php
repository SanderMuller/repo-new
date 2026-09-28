<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerJson;

/**
 * Sets the composer.json license. The repo-init stubs ship MIT (LICENSE,
 * README); for a proprietary scaffold, rewrite those too.
 */
final class LicenseApplier
{
    public const array LICENSES = ['MIT', 'proprietary'];

    private const string README_MIT_LINE = 'MIT — see [LICENSE](LICENSE).';

    public function apply(string $targetDir, string $license): void
    {
        $composerPath = $targetDir . '/composer.json';
        $json = ComposerJson::read($composerPath);
        if (($json['license'] ?? null) !== $license) {
            $json['license'] = $license;
            ComposerJson::write($composerPath, $json);
        }

        if ($license === 'MIT') {
            return;
        }

        if (is_file($targetDir . '/LICENSE') && ! unlink($targetDir . '/LICENSE')) {
            throw new RuntimeException("Failed to delete {$targetDir}/LICENSE");
        }

        $this->rewriteReadme($targetDir . '/README.md');
    }

    private function rewriteReadme(string $path): void
    {
        $readme = is_file($path) ? file_get_contents($path) : false;
        if ($readme === false) {
            return;
        }

        $updated = str_replace(self::README_MIT_LINE, 'Proprietary. All rights reserved.', $readme);
        // The Packagist license badge links the deleted LICENSE file.
        $updated = (string) preg_replace('#^\[!\[License\]\([^)]*packagist/l/[^)]*\)\]\(LICENSE\)\R#m', '', $updated);

        if ($updated !== $readme && file_put_contents($path, $updated) === false) {
            throw new RuntimeException("Failed to write {$path}");
        }
    }
}
