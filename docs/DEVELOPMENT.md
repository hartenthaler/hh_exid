# Development

## Working copy

Keep the canonical Git working copy below `Documents/Codex/webtrees-modules` and expose it to the local webtrees test installation through an NTFS junction. Editing through either path changes the same files.

Before starting work:

```powershell
Get-HhModuleStatus.ps1 -ShowDetails
```

Before creating a pull request:

```powershell
Test-WebtreesModule.ps1 -ModulePath .
```

## Translations

Module-specific strings belong in PO files. Compile every changed PO file to its MO counterpart. Strings already translated by webtrees should be called through `MoreI18N`, keeping them out of the module catalog. The module reads MO files through the webtrees 2.3 `Fisharebest\\Webtrees\\I18N\\Translation` API and falls back to the webtrees 2.2 `Fisharebest\\Localization\\Translation` API.

## Administrator-owned authority catalogue

The tracked `resources/config/exid-authorities.json` file is the initial seed
only. The administration page copies it on first use to
`data/hh_exid/exid-authorities.json`, using webtrees' configured data
filesystem. All later edits are written to that data-directory copy, so an
upgrade or replacement of the module cannot overwrite administrator changes.

When changing the catalogue format, keep the seed and the persisted copy
backwards-compatible. Validate the JSON before saving and provide an explicit
migration or reset path for incompatible changes; never silently discard the
administrator's data. The data-directory copy belongs in the site's normal
backup and migration process.

The seed contains a `defaults_version` number. When this number increases,
`AuthorityCatalogueStorage` adds only bundled authority definitions whose keys
are not already present in the administrator-owned copy. It then stores the
new version. Existing definitions are never overwritten, and an administrator
can remove a bundled authority after the migration without it being restored on
every request. A future seed change that adds another bundled authority must
increase `defaults_version` and be tested against an existing data-directory
copy.

## Context-aware authority selection

Each authority definition may contain a `contexts` list. In the admin form it
may be entered one per line or comma-separated. It limits the TYPE
URIs offered by the editor to the current GEDCOM record context. Use the
record tag (`INDI`, `FAM`, `OBJE`, `REPO`, `SNOTE`, `SOUR` or `SUBM`), `PLAC`
for an EXID attached to a family/individual place structure, or `_LOC` for a
webtrees shared place. The wildcard `*` applies everywhere. For every explicit
context list, `SNOTE` is added automatically; only `*` suppresses the explicit
list because it already includes every context. Missing or empty
metadata is normalized to `*`, preserving older administrator-owned catalogues
and unclassified custom authorities.

Mappings for the bundled GEDCOM registry are centralized in
`src/Infrastructure/ExidContextCatalog.php`. Extend that mapping when a new
registry source file needs a narrower context; do not duplicate mappings in
the module or view. The administration table exposes the contexts so an
administrator can review or refine them without editing JSON directly.
The optional `value_pattern` field is a regular expression that must match an
identifier before the linker creates a URL. If it is empty, the catalog uses
the safe default for printable values up to 200 characters. Screenshots for
this interface belong in `docs/screenshots/`; use anonymized test data and
stable filenames.
Those edits are stored separately in `data/hh_exid/gedcom-exid-contexts.json`;
the registry snapshot remains read-only. Resetting the registry contexts removes
the overrides and reactivates the central defaults.

## Releases

Keep meaningful user-facing changes compared with the previous development
stage in the `Next release` section of `CHANGELOG.md`. Do not list the
template origin of the module or internal bug fixes made within the current
stage; those details belong in commits and pull requests, not in the user-
facing changelog. Release notes are generated from this section. Build assets
from tracked files only.
