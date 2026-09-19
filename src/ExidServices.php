<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule;

use Hartenthaler\Webtrees\Module\ExidModule\Domain\ExternalIdentifierValue;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierLinker;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierParser;

/**
 * Public, provider-independent services for modules that consume EXID data.
 */
final class ExidServices
{
    private static ?ExternalIdentifierCatalog $catalog = null;

    public static function catalog(): ExternalIdentifierCatalog
    {
        return self::$catalog ??= ExternalIdentifierCatalog::fromJsonFile(__DIR__ . '/../resources/config/exid-authorities.json');
    }

    public static function linker(): ExternalIdentifierLinker
    {
        return new ExternalIdentifierLinker(self::catalog());
    }

    /** @return list<ExternalIdentifierValue> */
    public static function parse(string $gedcom): array
    {
        return (new ExternalIdentifierParser())->parse($gedcom);
    }
}
