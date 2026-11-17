<?php declare(strict_types=1);

use SanderMuller\RepoNew\Scaffolder\LaravelSkeletonFixer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The lines below are copied from the laravel/laravel skeleton that
 * `laravel new` wrote on 2026-11-17.
 *
 * @return array<string, string>
 */
function laravelSkeletonFiles(): array
{
    return [
        'app/Models/User.php' => <<<'PHP'
            <?php

            namespace App\Models;

            use Database\Factories\UserFactory;
            use Illuminate\Database\Eloquent\Factories\HasFactory;
            use Illuminate\Foundation\Auth\User as Authenticatable;
            use Illuminate\Notifications\Notifiable;

            class User extends Authenticatable
            {
                /** @use HasFactory<UserFactory> */
                use HasFactory, Notifiable;
            }

            PHP,
        'config/app.php' => <<<'PHP'
            <?php

            return [
                'previous_keys' => [
                    ...array_filter(
                        explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
                    ),
                ],
            ];

            PHP,
        'config/database.php' => <<<'PHP'
            <?php

            use Pdo\Mysql;

            return [
                'connections' => [
                    'mysql' => [
                        'options' => extension_loaded('pdo_mysql') ? array_filter([
                            Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                        ]) : [],
                    ],
                    'mariadb' => [
                        'options' => extension_loaded('pdo_mysql') ? array_filter([
                            Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                        ]) : [],
                    ],
                ],
            ];

            PHP,
        'config/filesystems.php' => <<<'PHP'
            <?php

            return [
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            ];

            PHP,
        'database/factories/UserFactory.php' => <<<'PHP'
            <?php

            namespace Database\Factories;

            use App\Models\User;
            use Illuminate\Database\Eloquent\Factories\Factory;

            /**
             * @extends Factory<User>
             */
            class UserFactory extends Factory
            {
                /**
                 * Define the model's default state.
                 *
                 * @return array<string, mixed>
                 */
                public function definition(): array
                {
                    return [];
                }
            }

            PHP,
    ];
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir() . '/repo-new-skeleton-' . bin2hex(random_bytes(4));
    foreach (laravelSkeletonFiles() as $file => $contents) {
        @mkdir(dirname($this->tmp . '/' . $file), 0755, true);
        file_put_contents($this->tmp . '/' . $file, $contents);
    }

    $this->output = new BufferedOutput();
    $this->fixer = new LaravelSkeletonFixer(new SymfonyStyle(new ArrayInput([]), $this->output));
});

afterEach(function (): void {
    removeDirectory($this->tmp);
});

it('fixes every skeleton line that fails the canonical PHPStan config', function (): void {
    $this->fixer->apply($this->tmp);

    expect(readFileContents($this->tmp . '/app/Models/User.php'))
        ->toContain("    /** @use HasFactory<UserFactory> */\n    use HasFactory;\n    use Notifiable;\n")
        ->and(readFileContents($this->tmp . '/config/app.php'))->toContain("static fn (string \$key): bool => \$key !== ''")
        ->and(substr_count(readFileContents($this->tmp . '/config/database.php'), "], static fn (mixed \$value): bool => \$value !== null && \$value !== '') : [],"))->toBe(2)
        ->and(readFileContents($this->tmp . '/config/filesystems.php'))->toContain("rtrim((string) env('APP_URL', 'http://localhost'), '/')")
        ->and(readFileContents($this->tmp . '/database/factories/UserFactory.php'))->toContain('@return array<model-property<User>, mixed>')
        ->and($this->output->fetch())->not->toContain('Skeleton fix skipped');

    foreach (array_keys(laravelSkeletonFiles()) as $file) {
        expect(phpLints($this->tmp . '/' . $file))->toBeTrue();
    }
});

it('is idempotent', function (): void {
    $this->fixer->apply($this->tmp);
    $first = array_map(fn (string $file): string => readFileContents($this->tmp . '/' . $file), array_keys(laravelSkeletonFiles()));

    $this->fixer->apply($this->tmp);
    $second = array_map(fn (string $file): string => readFileContents($this->tmp . '/' . $file), array_keys(laravelSkeletonFiles()));

    expect($second)->toBe($first);
});

it('warns and leaves the file alone when the skeleton line changed', function (): void {
    $changed = "<?php\n\nreturn ['url' => env('APP_URL') . '/storage'];\n";
    file_put_contents($this->tmp . '/config/filesystems.php', $changed);

    $this->fixer->apply($this->tmp);

    expect(readFileContents($this->tmp . '/config/filesystems.php'))->toBe($changed)
        ->and($this->output->fetch())->toContain('Skeleton fix skipped for config/filesystems.php');
});
