<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

use Fisharebest\Webtrees\Registry;
use RuntimeException;

use function is_dir;
use function is_writable;

/**
 * Persist the administrator-owned authority catalogue outside the module.
 *
 * The module file is a read-only seed.  The active copy is stored through
 * webtrees' configured data filesystem so module updates cannot overwrite it.
 */
final class AuthorityCatalogueStorage
{
    private const DATA_DIRECTORY = 'hh_exid';
    private const DATA_FILE = 'exid-authorities.json';

    public static function load(): ExternalIdentifierCatalog
    {
        $filesystem = Registry::filesystem()->data(self::DATA_DIRECTORY);
        $seed = ExternalIdentifierCatalog::fromJsonFile(__DIR__ . '/../../resources/config/exid-authorities.json');

        if ($filesystem->fileExists(self::DATA_FILE)) {
            $catalogue = ExternalIdentifierCatalog::fromJson($filesystem->read(self::DATA_FILE));

            if ($catalogue->mergeMissingDefaults($seed)) {
                // A migration failure must not prevent the module from using
                // the migrated in-memory catalogue during this request.
                try {
                    self::save($catalogue);
                } catch (\Throwable) {
                    // The next request will retry the migration.
                }
            }

            return $catalogue;
        }

        // Seed the data directory once.  A failed seed write must not prevent
        // the module from working with its bundled defaults.
        try {
            $filesystem->write(self::DATA_FILE, $catalogue->toJson());
        } catch (\Throwable) {
            // The administration page reports the writeability problem.
        }

        return $catalogue;
    }

    public static function save(ExternalIdentifierCatalog $catalogue): void
    {
        $filesystem = Registry::filesystem()->data(self::DATA_DIRECTORY);
        $temporary = self::DATA_FILE . '.tmp-' . bin2hex(random_bytes(8));

        try {
            $filesystem->write($temporary, $catalogue->toJson());
            $filesystem->move($temporary, self::DATA_FILE);
        } catch (\Throwable $exception) {
            try {
                $filesystem->delete($temporary);
            } catch (\Throwable) {
                // Preserve the original, useful exception for the admin.
            }

            throw new RuntimeException('The EXID authority catalogue could not be saved in the webtrees data directory.', 0, $exception);
        }
    }

    public static function isWritable(): bool
    {
        try {
            $dataDirectory = Registry::filesystem()->dataName();
            $moduleDirectory = rtrim($dataDirectory, '/\\') . DIRECTORY_SEPARATOR . self::DATA_DIRECTORY;

            return is_dir($moduleDirectory) ? is_writable($moduleDirectory) : is_writable($dataDirectory);
        } catch (\Throwable) {
            return false;
        }
    }
}
