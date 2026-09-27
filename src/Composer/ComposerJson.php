<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Composer;

use JsonException;
use RuntimeException;
use stdClass;

/**
 * write() re-encodes the empty MAP_PATHS maps as `{}`, not `[]`.
 */
final class ComposerJson
{
    /** Keys (dot paths) whose value is a JSON object even when empty. */
    private const array MAP_PATHS = [
        'require', 'require-dev', 'conflict', 'replace', 'provide', 'suggest',
        'autoload', 'autoload.psr-4', 'autoload-dev', 'autoload-dev.psr-4',
        'config', 'config.allow-plugins', 'scripts', 'extra',
    ];

    /**
     * @return array<mixed>
     */
    public static function read(string $path): array
    {
        $raw = is_file($path) ? file_get_contents($path) : false;
        if ($raw === false) {
            throw new RuntimeException('Failed to read ' . $path);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException('Invalid JSON in ' . $path . ': ' . $jsonException->getMessage(), 0, $jsonException);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException($path . ' is not a JSON object');
        }

        return $decoded;
    }

    /**
     * @param  array<mixed>  $json
     */
    public static function write(string $path, array $json): void
    {
        $encoded = json_encode(self::restoreEmptyMaps($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException('Failed to write ' . $path);
        }
    }

    /**
     * @param  array<mixed>  $json
     * @return array<mixed>
     */
    public static function map(array $json, string $key): array
    {
        $value = $json[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<mixed>  $json
     * @return array<mixed>
     */
    private static function restoreEmptyMaps(array $json, string $prefix = ''): array
    {
        foreach ($json as $key => $value) {
            $path = $prefix . $key;
            if (! in_array($path, self::MAP_PATHS, true) || ! is_array($value)) {
                continue;
            }

            $json[$key] = $value === [] ? new stdClass() : self::restoreEmptyMaps($value, $path . '.');
        }

        return $json;
    }
}
