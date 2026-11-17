<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fixes the lines of the `laravel new` skeleton that fail the canonical
 * PHPStan config (repo-init bootstrap-laravel-project.md step 10). Each fix is
 * an exact match: a newer skeleton that changed the line gets a warning, not a
 * guess.
 */
final readonly class LaravelSkeletonFixer
{
    /**
     * File => list of [search, replace]. Every occurrence of a search string
     * is replaced; config/database.php has the same block for mysql and mariadb.
     */
    private const array FIXES = [
        // Rector splits the multi-trait line too, but it drops the @use docblock.
        'app/Models/User.php' => [[
            "    /** @use HasFactory<UserFactory> */\n    use HasFactory, Notifiable;\n",
            "    /** @use HasFactory<UserFactory> */\n    use HasFactory;\n    use Notifiable;\n",
        ]],
        'config/app.php' => [[
            "            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))\n        ),",
            "            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),\n            static fn (string \$key): bool => \$key !== '',\n        ),",
        ]],
        'config/database.php' => [[
            "                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),\n            ]) : [],",
            "                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),\n            ], static fn (mixed \$value): bool => \$value !== null && \$value !== '') : [],",
        ]],
        'config/filesystems.php' => [[
            "rtrim(env('APP_URL', 'http://localhost'), '/')",
            "rtrim((string) env('APP_URL', 'http://localhost'), '/')",
        ]],
        'database/factories/UserFactory.php' => [[
            "     * @return array<string, mixed>\n     */\n    public function definition(): array",
            "     * @return array<model-property<User>, mixed>\n     */\n    public function definition(): array",
        ]],
    ];

    public function __construct(private SymfonyStyle $io) {}

    public function apply(string $targetDir): void
    {
        foreach (self::FIXES as $file => $fixes) {
            $path = $targetDir . '/' . $file;
            $contents = is_file($path) ? file_get_contents($path) : false;

            if ($contents === false) {
                $this->warnNotFixed($file, 'the file is missing');

                continue;
            }

            $updated = $contents;
            foreach ($fixes as [$search, $replace]) {
                if (! str_contains($updated, $search)) {
                    $this->warnNotFixed($file, 'the expected skeleton line was not found');

                    continue;
                }

                $updated = str_replace($search, $replace, $updated);
            }

            if ($updated !== $contents && file_put_contents($path, $updated) === false) {
                throw new RuntimeException("Failed to write {$path}");
            }
        }
    }

    private function warnNotFixed(string $file, string $reason): void
    {
        $this->io->warning("Skeleton fix skipped for {$file}: {$reason}. PHPStan can report it; see repo-init bootstrap-laravel-project.md step 10.");
    }
}
