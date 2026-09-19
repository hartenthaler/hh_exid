# hh_exid

This webtrees module provides reusable support for external identifiers.

It supports the standard GEDCOM 7 spelling `EXID` and the GEDCOM 5.5.1 custom
spelling `_EXID`. Each identifier may have one `TYPE` child containing the URI
of the external authority.

```gedcom
1 EXID Q498565
2 TYPE https://www.wikidata.org/entity/
```

Known authority URIs are resolved through an allow-listed catalogue and produce
safe HTTPS links. Unknown authorities remain escaped text, and multiple
identifiers are preserved.

## Features

- registers the supported `EXID` and `_EXID` structures, including `TYPE`;
- parses typed identifiers without guessing an authority from the value;
- provides safe links for known authorities;
- keeps the official GEDCOM 7 registry snapshot separate from application link templates;
- uses the standard webtrees PO/MO translation system.

The module is independent of provider modules. It can be used as a common
foundation by other webtrees modules, but it does not require them.

## Installation

Copy the `hh_exid` directory into `modules_v4` and enable the module in the
webtrees administration panel.

Its Composer package type is `webtrees-module`. The official
`webtrees/module-installer` is an optional installation helper for source
checkouts; it is not used at runtime.

## GEDCOM 7 registry

The registered GEDCOM 7 `EXID.TYPE` definitions are kept separately in
`resources/config/gedcom-exid-types.json`. They are a deduplicated snapshot of
the official [FamilySearch GEDCOM registries](https://github.com/FamilySearch/GEDCOM-registries/tree/main/uri/exid-types),
including the source commit used for the snapshot. Additional link templates stay
in `exid-authorities.json` and are deliberately maintained separately.

## Credits

Maintained by Hermann Hartenthaler with assistance from Codex.

## License

GPL-3.0-or-later, matching webtrees and the established hh module practice.
