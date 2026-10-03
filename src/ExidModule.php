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
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarTrait;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Hartenthaler\Webtrees\Module\ExidModule\Elements\ExtendedExternalIdentifier;
use Hartenthaler\Webtrees\Module\ExidModule\Elements\ExtendedExternalIdentifierType;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\AuthorityCatalogueStorage;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExidContextCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierCatalog;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\GedcomExidContextStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

use function file_exists;
use function array_key_exists;
use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function json_encode;
use function preg_split;
use function preg_match;
use function trim;

class ExidModule extends AbstractModule implements ModuleConfigInterface, ModuleCustomInterface, ModuleDataFixInterface, ModuleGlobalInterface, ModuleSidebarInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleDataFixTrait;
    use ModuleSidebarTrait;

    private const MODULE_NAME = 'hh_exid';
    private const GITHUB_USER = 'hartenthaler';
    private const PREFERENCE_EXID_TAG = 'exid_tag';
    private const PREFERENCE_DISPLAY_MODE = 'exid_display_mode';
    private const DISPLAY_MODE_SIDEBAR = 'sidebar';
    private const DISPLAY_MODE_FACTS = 'facts';
    private const FIX_OPERATION_FAMILYSEARCH = 'familysearch';
    private const FIX_OPERATION_REPLACE_TYPE = 'replace_type';
    private const FIX_OPERATION_PARAMETER = 'exid_fix_operation';
    private const OLD_TYPE_PARAMETER = 'old_type_uri';
    private const NEW_TYPE_PARAMETER = 'new_type_uri';
    public const TAG_EXID = 'EXID';
    public const TAG_LEGACY_EXID = '_EXID';
    private DataFixService $dataFixService;

    public function __construct(DataFixService $dataFixService)
    {
        $this->dataFixService = $dataFixService;
    }

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

        // Keep configuration on the external script element.  Inline scripts
        // can be blocked by a site's Content-Security-Policy, while data
        // attributes are available to the module asset without relaxing CSP.
        return '<script src="' . e($this->assetUrl('exid-type.js')) . '"' .
            ' data-hh-exid-type-uris="' . e((string) json_encode(array_keys($typeUris), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '"' .
            ' data-hh-exid-value-patterns="' . e((string) json_encode($valuePatterns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '"' .
            ' data-hh-exid-fact-labels="' . e((string) json_encode($factLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '"' .
            ' data-hh-exid-pattern-error="' . e($patternError) . '" defer></script>';
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        $catalogue = AuthorityCatalogueStorage::load();
        $gedcomTypes = ExidServices::gedcomTypeCatalog()->all();
        $contextOverrides = GedcomExidContextStorage::load();

        foreach ($gedcomTypes as $index => $type) {
            $gedcomTypes[$index]['contexts'] = ExidContextCatalog::normalize(
                $contextOverrides[$type['uri']] ?? ExidContextCatalog::registryContexts($type['source_file']),
            );
        }

        return $this->viewResponse($this->name() . '::configuration', [
            'title' => $this->title(),
            'description' => $this->description(),
            'selected_tag' => $this->preferredTag(),
            'display_mode' => $this->displayMode(),
            'authorities' => $catalogue->all(),
            'authority_catalogue_writable' => AuthorityCatalogueStorage::isWritable(),
            'gedcom_types' => $gedcomTypes,
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $tag = Validator::parsedBody($request)->string('exid_tag');
        $displayMode = Validator::parsedBody($request)->string('exid_display_mode');
        if (!in_array($tag, [self::TAG_EXID, self::TAG_LEGACY_EXID], true)) {
            FlashMessages::addMessage(I18N::translate('The selected EXID tag is invalid.'), 'danger');
        } else {
            $this->setPreference(self::PREFERENCE_EXID_TAG, $tag);
            FlashMessages::addMessage(I18N::translate('The EXID tag preference has been updated.'), 'success');
        }

        if (in_array($displayMode, [self::DISPLAY_MODE_SIDEBAR, self::DISPLAY_MODE_FACTS], true)) {
            $this->setPreference(self::PREFERENCE_DISPLAY_MODE, $displayMode);
            FlashMessages::addMessage(I18N::translate('The EXID display preference has been updated.'), 'success');
        } else {
            FlashMessages::addMessage(I18N::translate('The selected EXID display mode is invalid.'), 'danger');
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

        try {
            $body = $request->getParsedBody();
            $resetContexts = Validator::parsedBody($request)->boolean('reset_gedcom_contexts', false);
            $hasContexts = is_array($body) && array_key_exists('gedcom_contexts', $body);

            if ($resetContexts) {
                GedcomExidContextStorage::save([]);
                FlashMessages::addMessage(I18N::translate('The GEDCOM 7 registry context assignments were reset to their defaults.'), 'success');
            } elseif ($hasContexts) {
                GedcomExidContextStorage::save($this->gedcomContextOverridesFromRequest($request));
                FlashMessages::addMessage(I18N::translate('The GEDCOM 7 registry context assignments have been updated.'), 'success');
            }
        } catch (\Throwable $exception) {
            FlashMessages::addMessage(
                I18N::translate('The GEDCOM 7 registry context assignments could not be updated.') . ' ' . $exception->getMessage(),
                'danger',
            );
        }

        return redirect($this->getConfigLink());
    }

    /** @return array<string,list<string>> */
    private function gedcomContextOverridesFromRequest(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        $rows = is_array($body) && is_array($body['gedcom_contexts'] ?? null) ? $body['gedcom_contexts'] : [];
        $overrides = [];

        foreach (ExidServices::gedcomTypeCatalog()->all() as $index => $type) {
            $values = is_string($rows[$index] ?? null) ? $this->contextValues($rows[$index]) : [];
            if ($values !== []) {
                $overrides[$type['uri']] = ExidContextCatalog::normalize($values);
            }
        }

        return $overrides;
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
                'contexts'      => $this->contextValues($row['contexts'] ?? ''),
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

    /** @return list<string> */
    private function contextValues(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $context): string => trim($context),
            preg_split('/[\r\n,]+/u', $value) ?: [],
        ), static fn (string $context): bool => $context !== ''));
    }

    public function preferredTag(): string
    {
        $tag = $this->getPreference(self::PREFERENCE_EXID_TAG, self::TAG_LEGACY_EXID);

        return in_array($tag, [self::TAG_EXID, self::TAG_LEGACY_EXID], true) ? $tag : self::TAG_LEGACY_EXID;
    }

    /**
     * Where EXID facts are shown on an individual page.
     *
     * The sidebar mode follows the normal webtrees module behaviour: when
     * this sidebar is enabled, its EXID facts are removed from the facts tab.
     * If the sidebar is disabled in webtrees, the facts tab automatically
     * shows them again because supportedFacts() returns an empty collection
     * for modules that are not active as sidebars.
     */
    private function displayMode(): string
    {
        $mode = $this->getPreference(self::PREFERENCE_DISPLAY_MODE, self::DISPLAY_MODE_SIDEBAR);

        return in_array($mode, [self::DISPLAY_MODE_SIDEBAR, self::DISPLAY_MODE_FACTS], true)
            ? $mode
            : self::DISPLAY_MODE_SIDEBAR;
    }

    public function defaultSidebarOrder(): int
    {
        return 2;
    }

    public function hasSidebarContent(Individual $individual): bool
    {
        return $this->displayMode() === self::DISPLAY_MODE_SIDEBAR
            && $individual->facts([self::TAG_EXID, self::TAG_LEGACY_EXID])->isNotEmpty();
    }

    public function getSidebarContent(Individual $individual): string
    {
        if ($this->displayMode() !== self::DISPLAY_MODE_SIDEBAR) {
            return '';
        }

        $facts = $individual->facts([self::TAG_EXID, self::TAG_LEGACY_EXID]);
        $rows = $facts
            ->map(static fn (Fact $fact): string => view('fact', ['fact' => $fact, 'record' => $individual]))
            ->implode('');

        return '<table class="table table-sm mb-0"><tbody>' . strip_tags(
            $rows,
            '<table><tbody><tr><th><td><div><span><a><i><br><button><form><input>',
        ) . '</tbody></table>';
    }

    public function supportedFacts(): Collection
    {
        if ($this->displayMode() !== self::DISPLAY_MODE_SIDEBAR) {
            return new Collection();
        }

        return new Collection(['INDI:' . self::TAG_EXID, 'INDI:' . self::TAG_LEGACY_EXID]);
    }

    /**
     * Describe the EXID data corrections shown in the data-fix menu.
     */
    public function fixOptions(Tree $tree): string
    {
        return '<p>' . e(I18N::translate('Select the data correction to run.')) . '</p>' .
            '<div class="mb-3">' .
            '<label class="form-label" for="exid-fix-operation">' . e(I18N::translate('Data correction')) . '</label>' .
            '<select class="form-select" id="exid-fix-operation" name="' . self::FIX_OPERATION_PARAMETER . '">' .
            '<option value="' . self::FIX_OPERATION_FAMILYSEARCH . '">' . e(I18N::translate('Replace level-1 _FSFTID tags with a FamilySearch EXID')) . '</option>' .
            '<option value="' . self::FIX_OPERATION_REPLACE_TYPE . '">' . e(I18N::translate('Replace an EXID TYPE URI')) . '</option>' .
            '</select>' .
            '</div>' .
            '<div id="exid-type-replacement-fields" class="row mb-3 d-none">' .
            '<div class="col-md-6">' .
            '<label class="form-label" for="old-type-uri">' . e(I18N::translate('Current TYPE URI')) . '</label>' .
            '<input class="form-control font-monospace" type="text" id="old-type-uri" name="' . self::OLD_TYPE_PARAMETER . '" autocomplete="off">' .
            '</div>' .
            '<div class="col-md-6">' .
            '<label class="form-label" for="new-type-uri">' . e(I18N::translate('Replacement TYPE URI')) . '</label>' .
            '<input class="form-control font-monospace" type="text" id="new-type-uri" name="' . self::NEW_TYPE_PARAMETER . '" autocomplete="off">' .
            '</div>' .
            '<div class="col-12 form-text">' . e(I18N::translate('The replacement applies to TYPE children of EXID and _EXID in all supported GEDCOM record types.')) . '</div>' .
            '</div>' .
            '<p class="form-text">' . e(I18N::translate('The FamilySearch conversion uses the configured EXID or _EXID tag for new identifiers.')) . '</p>' .
            '<script>' .
            '(function () {' .
            'const operation = document.getElementById("exid-fix-operation");' .
            'const fields = document.getElementById("exid-type-replacement-fields");' .
            'if (!operation || !fields) { return; }' .
            'const update = function () { fields.classList.toggle("d-none", operation.value !== "' . self::FIX_OPERATION_REPLACE_TYPE . '"); };' .
            'operation.addEventListener("change", update);' .
            'update();' .
            '}());' .
            '</script>';
    }

    /**
     * XREFs of every record type that contains a legacy _FSFTID tag.
     *
     * The tag is normally found on individuals, but old imports and custom
     * workflows can place it in other records.  The data fix therefore scans
     * every record type supported by webtrees rather than silently assuming
     * INDI only.
     *
     * @return Collection<int,string>|null
     */
    protected function familiesToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->familiesToFixQuery($tree, $params), 'f_gedcom', $params)
            ->pluck('f_id');
    }

    protected function individualsToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->individualsToFixQuery($tree, $params), 'i_gedcom', $params)
            ->pluck('i_id');
    }

    protected function locationsToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->locationsToFixQuery($tree, $params), 'o_gedcom', $params)
            ->pluck('o_id');
    }

    protected function mediaToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->mediaToFixQuery($tree, $params), 'm_gedcom', $params)
            ->pluck('m_id');
    }

    protected function notesToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->notesToFixQuery($tree, $params), 'o_gedcom', $params)
            ->pluck('o_id');
    }

    protected function repositoriesToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->repositoriesToFixQuery($tree, $params), 'o_gedcom', $params)
            ->pluck('o_id');
    }

    protected function sourcesToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->sourcesToFixQuery($tree, $params), 's_gedcom', $params)
            ->pluck('s_id');
    }

    protected function submittersToFix(Tree $tree, array $params): ?Collection
    {
        return $this->candidateQuery($this->submittersToFixQuery($tree, $params), 'o_gedcom', $params)
            ->pluck('o_id');
    }

    private function candidateQuery(Builder $query, string $column, array $params): Builder
    {
        if ($this->fixOperation($params) === self::FIX_OPERATION_REPLACE_TYPE) {
            // The exact parent/TYPE relationship is checked in PHP.  The SQL
            // predicate only narrows the candidate set without making any
            // assumptions about the level at which an EXID occurs.
            return $query->where($column, 'LIKE', "%\n% TYPE %");
        }

        return $query->where($column, 'LIKE', "%\n1 _FSFTID %");
    }

    public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool
    {
        if ($this->fixOperation($params) === self::FIX_OPERATION_REPLACE_TYPE) {
            return $this->replaceTypeInExid($record->gedcom(), $params) !== $record->gedcom();
        }

        return preg_match('/^1 _FSFTID\s+\S+/mu', $record->gedcom()) === 1;
    }

    public function previewUpdate(GedcomRecord $record, array $params): string
    {
        $newGedcom = $this->updatedGedcom($record->gedcom(), $params);

        return $this->dataFixService->gedcomDiff(
            $record->tree(),
            $record->gedcom(),
            $newGedcom,
        );
    }

    public function updateRecord(GedcomRecord $record, array $params): void
    {
        $oldGedcom = $record->gedcom();
        $newGedcom = $this->updatedGedcom($oldGedcom, $params);

        if ($newGedcom !== $oldGedcom) {
            $record->updateRecord($newGedcom, false);
        }
    }

    private function updatedGedcom(string $gedcom, array $params): string
    {
        if ($this->fixOperation($params) === self::FIX_OPERATION_REPLACE_TYPE) {
            return $this->replaceTypeInExid($gedcom, $params);
        }

        return $this->convertLegacyFamilySearchIds($gedcom);
    }

    private function fixOperation(array $params): string
    {
        return ($params[self::FIX_OPERATION_PARAMETER] ?? '') === self::FIX_OPERATION_REPLACE_TYPE
            ? self::FIX_OPERATION_REPLACE_TYPE
            : self::FIX_OPERATION_FAMILYSEARCH;
    }

    /** @return array{old:string,new:string}|null */
    private function typeReplacement(array $params): ?array
    {
        $old = trim((string) ($params[self::OLD_TYPE_PARAMETER] ?? ''));
        $new = trim((string) ($params[self::NEW_TYPE_PARAMETER] ?? ''));

        if ($old === '' || $new === '' || $old === $new) {
            return null;
        }

        $uriPattern = '/^[A-Za-z][A-Za-z0-9+.-]*:[^\s]+$/u';

        return preg_match($uriPattern, $old) === 1 && preg_match($uriPattern, $new) === 1
            ? ['old' => $old, 'new' => $new]
            : null;
    }

    private function replaceTypeInExid(string $gedcom, array $params): string
    {
        $replacement = $this->typeReplacement($params);
        if ($replacement === null) {
            return $gedcom;
        }

        $lines = preg_split('/\R/u', $gedcom) ?: [];
        $stack = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^(\d+)\s+([^\s]+)(?:\s+(.*))?$/u', $line, $match) !== 1) {
                continue;
            }

            $level = (int) $match[1];
            $tag = $match[2];
            $value = trim((string) ($match[3] ?? ''));
            $parent = $level > 0 ? ($stack[$level - 1] ?? null) : null;

            if ($tag === 'TYPE' && is_array($parent) && in_array($parent['tag'], [self::TAG_EXID, self::TAG_LEGACY_EXID], true) && $value === $replacement['old']) {
                $lines[$index] = preg_replace_callback(
                    '/^(\d+\s+TYPE\s+).*$/u',
                    static function (array $lineMatch) use ($replacement): string {
                        return $lineMatch[1] . $replacement['new'];
                    },
                    $line,
                ) ?? $line;
            }

            $stack = array_slice($stack, 0, $level);
            $stack[$level] = ['tag' => $tag];
        }

        return implode("\n", $lines);
    }

    private function convertLegacyFamilySearchIds(string $gedcom): string
    {
        $lines = preg_split('/\R/u', $gedcom) ?: [];
        $converted = [];

        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $line = $lines[$index];

            if (preg_match('/^1 _FSFTID\s+(\S+)\s*$/u', $line, $match) !== 1) {
                $converted[] = $line;
                continue;
            }

            $id = $match[1];
            $converted[] = '1 ' . $this->preferredTag() . ' ' . $id;
            $converted[] = '2 TYPE ' . $this->familySearchPersonTypeUri();

            // A few imports add a TYPE child to _FSFTID.  Replace it rather
            // than leaving two TYPE children on the newly created EXID.
            if (($lines[$index + 1] ?? '') !== '' && preg_match('/^2 TYPE(?:\s|$)/u', $lines[$index + 1]) === 1) {
                $index++;
            }
        }

        return implode("\n", $converted);
    }

    private function familySearchPersonTypeUri(): string
    {
        $type = ExidServices::gedcomTypeCatalog()->findBySourceFile('FamilySearch-PersonId.yaml');

        if ($type === null) {
            throw new \RuntimeException('The GEDCOM EXID type catalogue does not contain FamilySearch-PersonId.yaml.');
        }

        return $type['uri'];
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
        $type = fn (string $context): ExtendedExternalIdentifierType => new ExtendedExternalIdentifierType(
            MoreI18N::xlate('Type'),
            $this->exidTypeLabels($context),
        );

        $tags = [];

        foreach (self::EXID_RECORD_TYPES as $recordType) {
            foreach ([self::TAG_EXID, self::TAG_LEGACY_EXID] as $exidTag) {
                $tags[$recordType . ':' . $exidTag]      = $element();
                $tags[$recordType . ':' . $exidTag . ':TYPE'] = $type($recordType);
            }
        }

        foreach (self::PLACE_EXID_CONTEXTS as $placeContext) {
            foreach ([self::TAG_EXID, self::TAG_LEGACY_EXID] as $exidTag) {
                $tags[$placeContext . ':' . $exidTag]      = $element();
                $tags[$placeContext . ':' . $exidTag . ':TYPE'] = $type(ExidContextCatalog::PLAC);
            }
        }

        foreach ([self::TAG_EXID, self::TAG_LEGACY_EXID] as $exidTag) {
            $tags['_LOC:' . $exidTag]      = $element();
            $tags['_LOC:' . $exidTag . ':TYPE'] = $type('_LOC');
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
    private function exidTypeLabels(string $context): array
    {
        $labels = [];

        foreach (ExidServices::catalog()->forContext($context) as $authority) {
            foreach ($authority['type_uris'] as $uri) {
                $labels[$uri] ??= $authority['label'] . ' — ' . $uri;
            }
        }

        return $labels;
    }
}
