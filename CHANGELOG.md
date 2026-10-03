# Change Log

## Next release

- Added an administrator choice for displaying EXID values in the standard
  individual sidebar or always in the **Facts and events** tab. In standard
  mode, disabling the sidebar falls back to the facts tab.
- Added an administrator data correction to replace an EXID `TYPE` URI across
  all supported GEDCOM record types, including both `EXID` and `_EXID`.
- Fixed the Online-OFB authority definition to use HTTPS and migrate the
  previously stored HTTP URI automatically.
- Added an administrator data-fix for converting legacy level-1 `_FSFTID`
  tags into the configured `EXID` or `_EXID` spelling with the configured
  FamilySearch Person ID URI as `TYPE`.
- Retained a local compatibility override for the FamilySearch Person ID URL,
  because FamilySearch currently provides no guarantee of permanently stable
  public person-record URIs.
- Added the confirmed GEDBAS and DePeVe person-record URL templates from the
  Genealogienetz portal to the bundled authority catalogue. Existing
  administrator catalogues receive these new defaults through the versioned
  non-destructive migration.
- Restricted both authorities to the meaningful `INDI`, `SOUR` and `SNOTE`
  contexts.
- Added reviewed identifier URL patterns for DES, Online-OFB and
  Adressbücher, including numeric, composite OFB and UUID value validation.
- Added and verified URI definitions for Geni, geneee, Roglo, XING, WeRelate,
  VIAF, Instagram, Facebook and LinkedIn for `INDI`, `SOUR` and `SNOTE`
  records.

## 2.2.6.3 - 2026-09-26

- Added context-aware EXID TYPE selection for GEDCOM record types and place
  contexts, with automatic `SNOTE` support and `_LOC`/`PLAC` handling.
- Added administrator assignment and reset controls for compatible contexts of
  the read-only GEDCOM 7 EXID registry.
- Made the authority value pattern optional; an empty pattern accepts safe
  identifier values using the module's default validation.
- Added language-specific Wikipedia authority URIs for safe clickable links to
  Wikipedia page names stored as EXID values.
- Existing administrator-owned authority catalogues now receive new bundled
  default authorities through a versioned, non-destructive migration.

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
