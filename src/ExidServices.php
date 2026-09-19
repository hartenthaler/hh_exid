<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule;

use Hartenthaler\Webtrees\Module\ExidModule\Domain\ExternalIdentifierValue;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierLinker;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierParser;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\GedcomExidTypeCatalog;

/**
 * Public, provider-independent services for modules that consume EXID data.
 */
final class ExidServices
{
    private static ?ExternalIdentifierCatalog $catalog = null;
    private static ?GedcomExidTypeCatalog $gedcomTypeCatalog = null;

    public static function catalog(): ExternalIdentifierCatalog
    {
        return self::$catalog ??= ExternalIdentifierCatalog::fromJsonFile(__DIR__ . '/../resources/config/exid-authorities.json')
            ->mergeRegistry(self::gedcomTypeCatalog());
    }

    public static function linker(): ExternalIdentifierLinker
    {
        return new ExternalIdentifierLinker(self::catalog());
    }

    public static function gedcomTypeCatalog(): GedcomExidTypeCatalog
    {
        return self::$gedcomTypeCatalog ??= GedcomExidTypeCatalog::fromJsonFile(__DIR__ . '/../resources/config/gedcom-exid-types.json');
    }

    /** @return list<ExternalIdentifierValue> */
    public static function parse(string $gedcom): array
    {
        return (new ExternalIdentifierParser())->parse($gedcom);
    }
}
