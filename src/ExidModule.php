<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;

use function file_exists;

class ExidModule extends AbstractModule implements ModuleCustomInterface
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
     * Vesta _LOC record, so these paths are added by this independent module.
     * Both the GEDCOM 5.5.1 custom spelling (_EXID) and the GEDCOM 7 spelling
     * (EXID) are accepted.
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
        return [
            '_LOC:_EXID'      => new ExternalIdentifier(MoreI18N::xlate('External identifier')),
            '_LOC:_EXID:TYPE' => new ExternalIdentifierType(MoreI18N::xlate('Type')),
            '_LOC:EXID'       => new ExternalIdentifier(MoreI18N::xlate('External identifier')),
            '_LOC:EXID:TYPE'  => new ExternalIdentifierType(MoreI18N::xlate('Type')),
        ];
    }

    /**
     * @return array<string,array<int,array<int,string>>>
     */
    protected function customSubTags(): array
    {
        return [
            '_LOC'       => [['_EXID', '0:M'], ['EXID', '0:M']],
            '_LOC:_EXID' => [['TYPE', '0:1']],
            '_LOC:EXID'  => [['TYPE', '0:1']],
        ];
    }
}
