<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule;

use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Hartenthaler\Webtrees\Module\ExidModule\Elements\ExtendedExternalIdentifier;
use Hartenthaler\Webtrees\Module\ExidModule\Elements\ExtendedExternalIdentifierType;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\GedcomExidTypeCatalog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function file_exists;

class ExidModule extends AbstractModule implements ModuleConfigInterface, ModuleCustomInterface, ModuleGlobalInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;

    private const MODULE_NAME = 'hh_exid';
    private const GITHUB_USER = 'hartenthaler';
    private const PREFERENCE_EXID_TAG = 'exid_tag';
    public const TAG_EXID = 'EXID';
    public const TAG_LEGACY_EXID = '_EXID';

    public function title(): string
    {
        return I18N::translate('External identifiers (EXID)');
    }

    public function description(): string
    {
        return I18N::translate('Support for GEDCOM external identifiers.');
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
        $typeUris = [];

        foreach (ExidServices::catalog()->all() as $authority) {
            foreach ($authority['type_uris'] as $uri) {
                $typeUris[$uri] = true;
            }
        }

        return '<script>window.hhExidTypeUris = ' . json_encode(array_keys($typeUris), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' .
            '<script src="' . e($this->assetUrl('exid-type.js')) . '" defer></script>';
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        return $this->viewResponse($this->name() . '::configuration', [
            'title' => $this->title(),
            'description' => $this->description(),
            'selected_tag' => $this->preferredTag(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $tag = Validator::parsedBody($request)->string('exid_tag');
        if (!in_array($tag, [self::TAG_EXID, self::TAG_LEGACY_EXID], true)) {
            FlashMessages::addMessage(I18N::translate('The selected EXID tag is invalid.'), 'danger');
        } else {
            $this->setPreference(self::PREFERENCE_EXID_TAG, $tag);
            FlashMessages::addMessage(I18N::translate('The EXID tag preference has been updated.'), 'success');
        }

        return redirect($this->getConfigLink());
    }

    public function preferredTag(): string
    {
        $tag = $this->getPreference(self::PREFERENCE_EXID_TAG, self::TAG_LEGACY_EXID);

        return in_array($tag, [self::TAG_EXID, self::TAG_LEGACY_EXID], true) ? $tag : self::TAG_LEGACY_EXID;
    }

    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . 'lang/' . $language . '.mo';

        if (!file_exists($file)) {
            return [];
        }

        // webtrees 2.3 moved the MO reader into its own I18N namespace and
        // changed it to a stream-based factory. Keep the webtrees 2.2 class
        // as a fallback so the module remains compatible with both versions.
        $webtreesTranslation = 'Fisharebest\\Webtrees\\I18N\\Translation';
        if (class_exists($webtreesTranslation) && method_exists($webtreesTranslation, 'fromMoStream')) {
            $stream = fopen($file, 'rb');
            if ($stream === false) {
                return [];
            }
            try {
                return $webtreesTranslation::fromMoStream($stream)->toArray();
            } catch (\Throwable) {
                return [];
            } finally {
                fclose($stream);
            }
        }

        $legacyTranslation = 'Fisharebest\\Localization\\Translation';
        if (class_exists($legacyTranslation)) {
            try {
                return (new $legacyTranslation($file))->asArray();
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
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
        $tag = $this->preferredTag();

        return [
            'INDI'       => [[$tag, '0:M']],
            'INDI:EXID'  => [['TYPE', '0:1']],
            'INDI:_EXID' => [['TYPE', '0:1']],
            '_LOC'       => [[$tag, '0:M']],
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
