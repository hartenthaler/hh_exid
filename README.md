# hh_exid

This webtrees module provides reusable support for external identifiers.

It supports the standard GEDCOM 7 spelling `EXID` and the GEDCOM 5.5.1 custom
spelling `_EXID`. Each identifier may have one `TYPE` child containing the URI
of the external authority.

```gedcom
1 EXID Q498565
2 TYPE https://www.wikidata.org/entity/
```

The stored `TYPE` URI is the only authority information used for links: the
identifier value is appended directly to that URI after it has passed the
allow-list and value validation. If no `TYPE` is present, no link is created.
Unknown authorities remain escaped text, and multiple identifiers are preserved. The
identifier value is never classified by its spelling (for example, a `Q` value
is not assumed to belong to a particular provider).

## Features

- registers the supported `EXID` and `_EXID` structures, including `TYPE`;
- parses typed identifiers without guessing an authority from the value;
- provides safe links for known authorities;
- keeps the official GEDCOM 7 registry snapshot separate from the link allow-list;
- uses the standard webtrees PO/MO translation system.

## Editing the TYPE value

When an EXID is entered or edited, the `TYPE` field offers the registered
authority URIs as a selection. Use the `+` button next to the field to enter a
different URI as free text. Existing values that are not in the catalogue are
shown in the free-text mode automatically, so that they can be preserved and
edited without being lost.

The module is independent of provider modules. It can be used as a common
foundation by other webtrees modules, but it does not require them.

## Installation

Copy the `hh_exid` directory into `modules_v4` and enable the module in the
webtrees administration panel.

Its Composer package type is `webtrees-module`. The official
`webtrees/module-installer` is an optional installation helper for source
checkouts; it is not used at runtime.

## GEDCOM 7 registry

The registered EXID type definitions are kept separately in
`resources/config/gedcom-exid-types.json`. They are a deduplicated snapshot of
the [FamilySearch GEDCOM registries](https://github.com/FamilySearch/GEDCOM-registries/tree/main/uri/exid-types),
including the source commit used for the snapshot. A provider URI such as GOV
comes from the registry or, only when it is not registered there, the explicit
provider catalogue; it is never inferred from an EXID and is not a substitute
for a missing TYPE. If the same URI occurs in both sources, the registry is
authoritative and the provider definition is discarded.

## Credits

Maintained by Hermann Hartenthaler with assistance from Codex.

## License

GPL-3.0-or-later, matching webtrees and the established hh module practice.
