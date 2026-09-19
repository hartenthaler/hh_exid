# hh_exid

This webtrees module registers external identifiers.

It supports the GEDCOM 5.5.1 custom spelling `_EXID` and the GEDCOM 7 spelling
`EXID`. Each identifier may have one `TYPE` child containing the
URI of the external authority.

```gedcom
1 _LOC
2 _EXID Q498565
3 TYPE https://www.wikidata.org/entity/
```

Known authority
URIs are resolved through an allow-listed catalogue and produce safe HTTPS
links; unknown authorities remain escaped text. Multiple identifiers are
preserved.

## Included

- registration of `_LOC:_EXID` and `_LOC:EXID` including their `TYPE` child;
- a valid custom module bootstrap and module class;
- gettext PO/MO loading and a separate wrapper for webtrees core translations;

## Installation

Copy the `hh_exid` directory into `modules_v4` and enable the module in the
webtrees administration panel. Its Composer package type is `webtrees-module`; the official
`webtrees/module-installer` is only an optional installation helper for source
checkouts and is not used at runtime.

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
