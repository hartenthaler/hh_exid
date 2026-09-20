# Change Log

## Next release


## 2.2.6.2 - 2026-09-21

- Added an administration editor for the module's EXID authority catalogue.
  It validates URIs, hosts, identifier patterns and duplicates and stores the
  administrator-owned copy in webtrees' data directory.
- Added a compact, read-only comparison table for the official GEDCOM 7 EXID
  registry.
- Registered both `EXID` and `_EXID` across all GEDCOM record contexts supported
  by the standard: `FAM`, `INDI`, `OBJE`, `REPO`, `SNOTE`, `SOUR` and `SUBM`.
- Extended support to the supported place-structure contexts and retained
  `_LOC` as a webtrees/Vesta extension.
- Added documentation explaining the complete EXID context matrix and the
  distinction between GEDCOM-standard and webtrees-specific contexts.

## 2.2.6.1 - 2026-09-20

- Administrators can choose whether newly added identifiers use the GEDCOM 7
  tag `EXID` or the GEDCOM 5.5.1-compatible `_EXID` tag.
- Existing identifiers using either spelling remain readable and editable.
- Other modules can query the selected tag through an optional public service;
  hh_exid remains usable without provider modules.
- Expanded the documentation with CMM, manual and Composer installation,
  security and privacy, translation, and development guidance.

## 2.2.6.0 - 2026-09-20

This is the first release of the module.

- Support GEDCOM 7 `EXID` and the GEDCOM 5.5.1-compatible `_EXID` spelling.
- Show external identifiers as safe clickable links when their stored `TYPE`
  URI is registered and valid.
- Offer registered `TYPE` URIs in the editor, with a `+` control for custom
  authority URIs.
- Make `EXID` available in the individual facts/events editor and avoid
  duplicate editor entries for the two GEDCOM spellings.
- Include the GEDCOM EXID type registry and use registered standard URIs as
  the authoritative definitions.
- Keep the module independent of provider modules and preserve unknown or
  custom identifier types without guessing their meaning.
