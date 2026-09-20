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
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\AuthorityCatalogueStorage;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\GedcomExidTypeCatalog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function file_exists;
use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function json_encode;
use function preg_split;
use function trim;

class ExidModule extends AbstractModule implements ModuleConfigInterface, ModuleCustomInterface, ModuleGlobalInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;

    private const MODULE_NAME = 'hh_exid';
    private const GITHUB_USER = 'hartenthaler';
    private const PREFERENCE_EXID_TAG = 'exid_tag';
    public const TAG_EXID = 'EXID';
    public const TAG_LEGACY_EXID = '_EXID';

    /**
     * GEDCOM 7 record types that directly contain IDENTIFIER_STRUCTURE.
     *
     * PLACE_STRUCTURE has separate EXID entries and is registered below for
     * the event contexts supported by webtrees' GEDCOM 7 catalogue.
     *
     * @var list<string>
     */
    private const EXID_RECORD_TYPES = ['FAM', 'INDI', 'OBJE', 'REPO', 'SNOTE', 'SOUR', 'SUBM'];

    /**
     * @var list<string>
     */
    private const PLACE_EXID_CONTEXTS = ['FAM:*:PLAC', 'INDI:*:PLAC'];

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
        $valuePatterns = [];

        foreach (ExidServices::catalog()->all() as $authority) {
            foreach ($authority['type_uris'] as $uri) {
                $typeUris[$uri] = true;
                $valuePatterns[$uri] = $authority['value_pattern'];
            }
        }

        $factLabels = [I18N::translate('External identifier'), 'EXID', '_EXID'];
        $patternError = I18N::translate('The external identifier does not match the selected authority.');

        return '<script>window.hhExidTypeUris = ' . json_encode(array_keys($typeUris), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '; window.hhExidValuePatterns = ' . json_encode($valuePatterns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '; window.hhExidFactLabels = ' . json_encode($factLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '; window.hhExidPatternError = ' . json_encode($patternError, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' .
            '<script src="' . e($this->assetUrl('exid-type.js')) . '" defer></script>';
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        $catalogue = AuthorityCatalogueStorage::load();

        return $this->viewResponse($this->name() . '::configuration', [
            'title' => $this->title(),
            'description' => $this->description(),
            'selected_tag' => $this->preferredTag(),
            'authorities' => $catalogue->all(),
            'authority_catalogue_writable' => AuthorityCatalogueStorage::isWritable(),
            'gedcom_types' => ExidServices::gedcomTypeCatalog()->all(),
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

        try {
            AuthorityCatalogueStorage::save($this->catalogueFromRequest($request));
            FlashMessages::addMessage(I18N::translate('The EXID authority catalogue has been updated.'), 'success');
        } catch (\Throwable $exception) {
            FlashMessages::addMessage(
                I18N::translate('The EXID authority catalogue could not be updated.') . ' ' . $exception->getMessage(),
                'danger',
            );
        }

        return redirect($this->getConfigLink());
    }

    private function catalogueFromRequest(ServerRequestInterface $request): ExternalIdentifierCatalog
    {
        $body = $request->getParsedBody();
        $rows = is_array($body) && is_array($body['authorities'] ?? null) ? $body['authorities'] : [];
        $definitions = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $definitions[] = [];
                continue;
            }

            $definitions[] = [
                'key'           => trim((string) ($row['key'] ?? '')),
                'label'         => trim((string) ($row['label'] ?? '')),
                'type_uris'     => $this->lines($row['type_uris'] ?? ''),
                'value_pattern' => trim((string) ($row['value_pattern'] ?? '')),
                'allowed_hosts' => $this->lines($row['allowed_hosts'] ?? ''),
            ];
        }

        $json = json_encode(['version' => 1, 'authorities' => $definitions]);

        if (!is_string($json)) {
            throw new \RuntimeException('The EXID authority catalogue could not be encoded.');
        }

        return ExternalIdentifierCatalog::fromJson($json);
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', $value) ?: [],
        ), static fn (string $line): bool => $line !== ''));
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
     * Register EXID in every GEDCOM record context supported by this module.
     *
     * The standard IDENTIFIER_STRUCTURE is not limited to INDI and _LOC:
     * GEDCOM 7 defines it for FAM, INDI, OBJE, REPO, SNOTE, SOUR and SUBM.
     * PLACE_STRUCTURE has its own EXID entry and is registered for the event
     * contexts used by webtrees. The Vesta _LOC record is retained as a
     * webtrees-specific extension. Both EXID and the GEDCOM 5.5.1 custom
     * spelling (_EXID) remain readable and editable.
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

        $tags = [];

        foreach (self::EXID_RECORD_TYPES as $recordType) {
            foreach ([self::TAG_EXID, self::TAG_LEGACY_EXID] as $exidTag) {
                $tags[$recordType . ':' . $exidTag]      = $element();
                $tags[$recordType . ':' . $exidTag . ':TYPE'] = $type();
            }
        }

        foreach (self::PLACE_EXID_CONTEXTS as $placeContext) {
            foreach ([self::TAG_EXID, self::TAG_LEGACY_EXID] as $exidTag) {
                $tags[$placeContext . ':' . $exidTag]      = $element();
                $tags[$placeContext . ':' . $exidTag . ':TYPE'] = $type();
            }
        }

        foreach ([self::TAG_EXID, self::TAG_LEGACY_EXID] as $exidTag) {
            $tags['_LOC:' . $exidTag]      = $element();
            $tags['_LOC:' . $exidTag . ':TYPE'] = $type();
        }

        return $tags;
    }

    /**
     * @return array<string,array<int,array<int,string>>>
     */
    protected function customSubTags(): array
    {
        $tag = $this->preferredTag();

        $subtags = [];

        foreach (self::EXID_RECORD_TYPES as $recordType) {
            $subtags[$recordType] = [[$tag, '0:M']];
            $subtags[$recordType . ':' . self::TAG_EXID] = [['TYPE', '0:1']];
            $subtags[$recordType . ':' . self::TAG_LEGACY_EXID] = [['TYPE', '0:1']];
        }

        foreach (self::PLACE_EXID_CONTEXTS as $placeContext) {
            $subtags[$placeContext] = [[$tag, '0:M']];
            $subtags[$placeContext . ':' . self::TAG_EXID] = [['TYPE', '0:1']];
            $subtags[$placeContext . ':' . self::TAG_LEGACY_EXID] = [['TYPE', '0:1']];
        }

        $subtags['_LOC'] = [[$tag, '0:M']];
        $subtags['_LOC:' . self::TAG_EXID] = [['TYPE', '0:1']];
        $subtags['_LOC:' . self::TAG_LEGACY_EXID] = [['TYPE', '0:1']];

        return $subtags;
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
