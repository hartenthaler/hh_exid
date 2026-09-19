# hh_exid

This webtrees module registers external identifiers for shared-place records.

It supports the GEDCOM 5.5.1 custom spelling `_EXID` and the GEDCOM 7 spelling
`EXID` below `_LOC`. Each identifier may have one `TYPE` child containing the
URI of the external authority.

```gedcom
1 _LOC
2 _EXID Q498565
3 TYPE https://www.wikidata.org/entity/
```

The module is intentionally independent of provider modules. Known authority
URIs are resolved through an allow-listed catalogue and produce safe HTTPS
links; unknown authorities remain escaped text. Multiple identifiers are
preserved.

## Included

- registration of `_LOC:_EXID` and `_LOC:EXID` including their `TYPE` child;
- a valid custom module bootstrap and module class;
- gettext PO/MO loading and a separate wrapper for webtrees core translations;
- `README.md`, `CHANGELOG.md`, `version.txt`, and development documentation;
- PHP and gettext checks in GitHub Actions;
- a tag-based release workflow that creates a ZIP asset with the correct module root folder;
- branch-safe defaults and the standard hh module release workflow.
- a `composer.json` declaring the `webtrees-module` package type and the
  official `webtrees/module-installer` dependency.

## Installation

Copy the `hh_exid` directory into `modules_v4` and enable the module in the
webtrees administration panel. The module has no dependency on provider
modules. Its Composer package type is `webtrees-module`; the official
`webtrees/module-installer` is only an optional installation helper for source
checkouts and is not used at runtime.

## Scope of version 2

This stage provides the parser and the public `ExidServices` facade for other
modules. It does not yet add an editor or a provider-specific page renderer.

## Credits

Maintained by Hermann Hartenthaler with assistance from Codex.

## License

GPL-3.0-or-later, matching webtrees and the established hh module practice.
