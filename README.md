# hh_exid

![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)
[![Module version](https://img.shields.io/badge/version-2.2.6.2-blue)](version.txt)
[![Downloads](https://img.shields.io/github/downloads/hartenthaler/hh_exid/total?label=downloads)](https://github.com/hartenthaler/hh_exid/releases)
[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

This webtrees module provides support for external identifiers.

## Contents

* [For users](#for-users)
* [Where EXID can occur](#where-exid-can-occur)
* [Editing the TYPE value](#editing-the-type-value)
* [For administrators](#for-administrators)
* [Choosing the tag for new identifiers](#choosing-the-tag-for-new-identifiers)
* [Managing the authority catalogue](#managing-the-authority-catalogue)
* [Requirements](#requirements)
* [Installation](#installation)
* [Security and privacy](#security-and-privacy)
* [Sources of known TYPE URIs](#sources-of-known-type-uris)
* [Translation](#translation)
* [Development](#development)
* [Credits](#credits)
* [License](#license)

## For users

### Supported GEDCOM structure

The module supports the standard GEDCOM 7 spelling `EXID` and the GEDCOM 5.5.1 custom
spelling `_EXID`. Each identifier may have one `TYPE` subtag containing the URI
of the external authority.

```gedcom
1 EXID Q498565
2 TYPE https://www.wikidata.org/entity/
```

The stored `TYPE` URI is the only authority information used for links: the
identifier value is appended directly to that URI after it has passed the
allow-list and value validation. If no `TYPE` is present, no link is created.
Unknown authorities remain escaped text, and multiple identifiers are preserved.

### Where EXID can occur

GEDCOM 7 defines the reusable identifier structure for family, individual,
multimedia, repository, shared-note, source and submitter records (`FAM`,
`INDI`, `OBJE`, `REPO`, `SNOTE`, `SOUR` and `SUBM`). Place structures attached
to family and individual events can also carry an `EXID`. The module supports
these contexts as well as webtrees/Vesta shared-place records (`_LOC`). Both
the standard `EXID` tag and the GEDCOM 5.5.1 custom spelling `_EXID` are
accepted in each supported context. A normal `NOTE` record is not itself an
identifier record; the shared-note record is `SNOTE`.

The complete context matrix and the distinction between GEDCOM-standard and
webtrees-specific contexts are documented in
[docs/gedcom-exid-contexts.md](docs/gedcom-exid-contexts.md).

### Editing the TYPE value

When an EXID is entered or edited, the `TYPE` field offers the registered
authority URIs as a selection. Use the `+` button next to the field to enter a
different URI as free text. Existing values that are not in the catalogue are
shown in the free-text mode automatically, so that they can be preserved and
edited without being lost.

## For administrators

### Choosing the tag for new identifiers

Administrators can choose in the module configuration whether newly created
identifiers use the GEDCOM 7 tag `EXID` or the GEDCOM 5.5.1 custom tag
`_EXID`. The default remains `_EXID` for compatibility with existing webtrees
installations. Modules that create identifiers can use the public
`ExidServices::preferredTag()` service when hh_exid is installed and active.
Such modules must detect the optional service and use `_EXID` themselves as a
safe fallback when hh_exid is unavailable.

### Managing the authority catalogue

The administration page shows the module-owned `exid-authorities.json` seed
catalogue in a table with validated fields for each authority. The active copy is stored as
`data/hh_exid/exid-authorities.json` in the webtrees data directory, so module
updates do not overwrite administrator changes. It is intended for reviewed
additional authorities:
each entry defines its label, one or more `TYPE` URIs, the accepted identifier
pattern and the allowed link host. The module validates the JSON and rejects
duplicate or unsafe definitions before replacing the file atomically.

The official GEDCOM 7 `gedcom-exid-types.json` snapshot is displayed below it in
a compact, read-only table. It is maintained from the FamilySearch registry and
cannot be changed through the administration page.

### Requirements

* webtrees 2.2 or 2.3;
* PHP version supported by the installed webtrees release.

### Installation

#### Custom Module Manager (CMM)

If the module is published in your Custom Module Manager catalogue:

1. Open **Control panel / Modules / Custom Module Manager** in webtrees.
2. Find **External identifiers (EXID)** and choose **Install module**.
3. Enable the module in **Control panel / Modules / Custom modules**.

#### Manual installation

1. Download the [latest release](https://github.com/hartenthaler/hh_exid/releases/latest).
2. Unzip it into the `modules_v4` directory of your webtrees installation.
3. Ensure that the directory is named `hh_exid`.
4. Enable **External identifiers (EXID)** in the webtrees administration panel.

#### Composer installation

The package type is `webtrees-module` and the module declares the official
[`webtrees/module-installer`](https://codeberg.org/webtrees/module-installer)
plugin. Composer installation is intended for webtrees source checkouts that
contain a root `composer.json`; standard webtrees release ZIP files do not
provide that file. In such a checkout, install the module with:

```bash
composer require hartenthaler/hh-exid
```

Composer may ask for permission to run the module installer. Updates use
`composer update hartenthaler/hh-exid`.

### Security and privacy

The module does not treat a stored identifier as an arbitrary URL. A link is
created only when the `TYPE` URI is registered in the bundled GEDCOM/provider
catalogue and the identifier value passes the corresponding validation. Values
with unknown authorities remain escaped text; if no `TYPE` is present, no link
is generated.

The module does not send genealogical data to external services. It only
renders the stored identifier and its validated authority link. Administrators
should still review the public visibility of identifiers before publishing a
family tree.

## Sources of known TYPE URIs

The selectable and linkable TYPE URIs come from two maintained sources:

1. the official [FamilySearch GEDCOM registry](https://github.com/FamilySearch/GEDCOM-registries/tree/main/uri/exid-types),
   stored as a documented snapshot in `resources/config/gedcom-exid-types.json`;
2. the module's own authority catalogue in `resources/config/exid-authorities.json`,
   which contains additional reviewed authorities.

The two sources are combined without duplicate URI entries. The FamilySearch
registry remains the reference for entries that it defines; the module
catalogue supplies additional reviewed authorities that are not part of that
registry. These catalogues only define which TYPE values are known and how
their links are validated; an identifier is linked only when its GEDCOM data
contains a matching TYPE value.

## Translation

The user interface uses the standard webtrees gettext system. Module-specific
strings are maintained in `resources/lang/default.pot` and translated in
language-specific `.po` files; the corresponding `.mo` files are loaded at
runtime. Strings already translated by webtrees core are deliberately routed
through the module's `MoreI18N` helper and are not duplicated in this module's
catalogue.

German is currently included. Contributions to the PO files are welcome.

## Development

The module is intentionally independent of provider modules. Its public
`ExidServices` class can be used by other modules without making hh_exid a
hard dependency; callers must provide a safe fallback when hh_exid is absent.
See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for translation, testing and
release guidance.

## Credits

Maintained by Hermann Hartenthaler with assistance from Codex.

## License

Copyright (C) 2026 Hermann Hartenthaler.

This module is licensed under [GPL-3.0-or-later](LICENSE), matching webtrees
and the established hh module practice.
