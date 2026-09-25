<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

use Fisharebest\Webtrees\Registry;
use RuntimeException;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function strlen;
use function trim;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Persist administrator overrides for GEDCOM registry contexts.
 *
 * The FamilySearch registry snapshot remains read-only.  Only explicit
 * administrator assignments are stored here; an empty file means that the
 * central defaults from ExidContextCatalog are active.
 */
final class GedcomExidContextStorage
{
    private const DATA_DIRECTORY = 'hh_exid';
    private const DATA_FILE = 'gedcom-exid-contexts.json';

    /** @return array<string,list<string>> */
    public static function load(): array
    {
        try {
            $filesystem = Registry::filesystem()->data(self::DATA_DIRECTORY);
            if (!$filesystem->fileExists(self::DATA_FILE)) {
                return [];
            }

            $json = $filesystem->read(self::DATA_FILE);
            if (strlen($json) > 200000) {
                return [];
            }

            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['contexts'] ?? null)) {
                return [];
            }

            $contexts = [];
            foreach ($data['contexts'] as $uri => $values) {
                if (!is_string($uri) || !is_array($values)) {
                    continue;
                }

                $normalized = ExidContextCatalog::normalize($values);
                $contexts[trim($uri)] = $normalized;
            }

            return $contexts;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string,mixed> $contexts */
    public static function save(array $contexts): void
    {
        $normalized = [];
        foreach ($contexts as $uri => $values) {
            if (!is_string($uri) || !is_array($values)) {
                continue;
            }

            $uri = trim($uri);
            if ($uri === '') {
                continue;
            }

            $normalized[$uri] = ExidContextCatalog::normalize($values);
        }

        $json = json_encode([
            'version'  => 1,
            'contexts' => $normalized,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new RuntimeException('The GEDCOM EXID context assignments could not be encoded.');
        }

        $filesystem = Registry::filesystem()->data(self::DATA_DIRECTORY);
        $temporary = self::DATA_FILE . '.tmp-' . bin2hex(random_bytes(8));

        try {
            $filesystem->write($temporary, $json . "\n");
            $filesystem->move($temporary, self::DATA_FILE);
        } catch (\Throwable $exception) {
            try {
                $filesystem->delete($temporary);
            } catch (\Throwable) {
                // Preserve the original exception.
            }

            throw new RuntimeException('The GEDCOM EXID context assignments could not be saved.', 0, $exception);
        }
    }
}
