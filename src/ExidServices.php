<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule;

use Fisharebest\Webtrees\Module\ModuleService;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\ExidModule\Domain\ExternalIdentifierValue;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierLinker;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierParser;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\GedcomExidTypeCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\AuthorityCatalogueStorage;

/**
 * Public, provider-independent services for modules that consume EXID data.
 */
final class ExidServices
{
    private static ?ExternalIdentifierCatalog $catalog = null;
    private static ?GedcomExidTypeCatalog $gedcomTypeCatalog = null;

    public static function catalog(): ExternalIdentifierCatalog
    {
        return self::$catalog ??= AuthorityCatalogueStorage::load()
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

    /**
     * Return the tag configured for newly created identifiers.
     *
     * Consumers may call this service without depending on the module class.
     * If the module is not active, the legacy spelling remains the safe
     * compatibility fallback.
     */
    public static function preferredTag(): string
    {
        try {
            $module = Registry::container()->get(ModuleService::class)->findByName('hh_exid', true);
            if ($module instanceof ExidModule) {
                return $module->preferredTag();
            }
        } catch (\Throwable) {
            // Optional integration must never prevent identifier handling.
        }

        return ExidModule::TAG_LEGACY_EXID;
    }
}
