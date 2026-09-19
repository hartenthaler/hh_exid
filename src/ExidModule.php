<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\ExidModule\Elements\ExtendedExternalIdentifier;
use Hartenthaler\Webtrees\Module\ExidModule\Elements\ExtendedExternalIdentifierType;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\GedcomExidTypeCatalog;

use function file_exists;

class ExidModule extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;

    private const MODULE_NAME = 'hh_exid';
    private const GITHUB_USER = 'hartenthaler';

    public function title(): string
    {
        return I18N::translate('External identifiers (EXID)');
    }

    public function description(): string
    {
        return I18N::translate('Support for GEDCOM external identifiers in shared places.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Hermann Hartenthaler';
    }

    public function customModuleVersion(): string
    {
        return trim((string) file_get_contents(__DIR__ . '/../version.txt'));
    }

    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::GITHUB_USER . '/' . self::MODULE_NAME . '/main/version.txt';
    }

    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_USER . '/' . self::MODULE_NAME;
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    public function headContent(): string
    {
        return '';
    }

    public function bodyContent(): string
    {
        return '<script src="' . e($this->assetUrl('exid-type.js')) . '" defer></script>';
    }

    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . 'lang/' . $language . '.mo';

        return file_exists($file) ? (new Translation($file))->asArray() : [];
    }

    /**
     * Register the EXID variants used by shared-place records.
     *
     * webtrees already registers standard GEDCOM 7 EXID elements for the
     * contexts covered by its core tag catalogue. Shared places use the
     * Vesta _LOC records and individual records need the same independent
     * registration for editing. Both the GEDCOM 5.5.1 custom spelling
     * (_EXID) and the GEDCOM 7 spelling (EXID) are accepted.
     */
    public function boot(): void
    {
        $elementFactory = Registry::elementFactory();
        $elementFactory->registerTags($this->customTags());
        $elementFactory->registerSubTags($this->customSubTags());
    }

    /**
     * @return array<string,\Fisharebest\Webtrees\Contracts\ElementInterface>
     */
    protected function customTags(): array
    {
        $element = static fn (): ExtendedExternalIdentifier => new ExtendedExternalIdentifier(
            MoreI18N::xlate('External identifier'),
            ExidServices::linker(),
        );
        $type = fn (): ExtendedExternalIdentifierType => new ExtendedExternalIdentifierType(
            MoreI18N::xlate('Type'),
            $this->exidTypeLabels(),
        );

        return [
            'INDI:EXID'       => $element(),
            'INDI:EXID:TYPE'  => $type(),
            'INDI:_EXID'      => $element(),
            'INDI:_EXID:TYPE' => $type(),
            '_LOC:_EXID'      => $element(),
            '_LOC:EXID'       => $element(),
            '_LOC:_EXID:TYPE' => $type(),
            '_LOC:EXID:TYPE'  => $type(),
        ];
    }

    /**
     * @return array<string,array<int,array<int,string>>>
     */
    protected function customSubTags(): array
    {
        return [
            'INDI'       => [['EXID', '0:M']],
            'INDI:EXID'  => [['TYPE', '0:1']],
            'INDI:_EXID' => [['TYPE', '0:1']],
            '_LOC'       => [['_EXID', '0:M']],
            '_LOC:_EXID' => [['TYPE', '0:1']],
            '_LOC:EXID'  => [['TYPE', '0:1']],
        ];
    }

    /** @return array<string,string> */
    private function exidTypeLabels(): array
    {
        $labels = [];

        foreach (GedcomExidTypeCatalog::fromJsonFile(__DIR__ . '/../resources/config/gedcom-exid-types.json')->all() as $type) {
            $labels[$type['uri']] = $type['label'] . ' — ' . $type['uri'];
        }

        foreach (ExidServices::catalog()->all() as $authority) {
            foreach ($authority['type_uris'] as $uri) {
                $labels[$uri] ??= $authority['label'] . ' — ' . $uri;
            }
        }

        return $labels;
    }
}
