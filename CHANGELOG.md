# Change Log

## Next release

- Add an administration editor for the module-owned `exid-authorities.json`
  catalogue, with validation for URIs, hosts, patterns and duplicate entries.
- Store the administrator-owned catalogue in webtrees' data directory instead
  of writing into the installed module directory.
- Show the official GEDCOM 7 EXID registry in a compact read-only comparison
  table on the same administration page.
- Replace the error-prone raw JSON editor with a validated table editor.
- Validate identifier values against the selected authority pattern before an
  editor form can be submitted.
- Render registered EXID links on individual pages as well as shared-place
  pages, including pages where webtrees uses its core EXID element.
- Register both EXID spellings across all GEDCOM record contexts supported by
  the standard (FAM, INDI, OBJE, REPO, SNOTE, SOUR and SUBM), including the
  supported place-structure contexts; document the complete context matrix.

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
