# GEDCOM EXID contexts

GEDCOM 7 defines `EXID` through the reusable `IDENTIFIER_STRUCTURE`. It is
not restricted to individuals or places. The structure is available directly
on these record types:

| Record | Meaning |
| --- | --- |
| `FAM` | family |
| `INDI` | individual |
| `OBJE` | multimedia object |
| `REPO` | repository |
| `SNOTE` | shared note |
| `SOUR` | source |
| `SUBM` | submitter |

The GEDCOM 7 `PLACE_STRUCTURE` also permits `EXID` below a place attached to
an event or fact. webtrees registers the family and individual event paths
(`FAM:*:PLAC:EXID` and `INDI:*:PLAC:EXID`), which are the contexts handled by
this module. In addition, webtrees/Vesta shared-place records use the custom
`_LOC` record; this module supports `EXID` and `_EXID` below `_LOC` as a
webtrees-specific extension.

For compatibility, every supported context accepts both spellings:

```gedcom
1 EXID Q183
2 TYPE https://www.wikidata.org/entity/
```

or, in a GEDCOM 5.5.1/custom file:

```gedcom
1 _EXID Q183
2 TYPE https://www.wikidata.org/entity/
```

The module’s configured preferred spelling is used when a new identifier is
added. Existing identifiers are not rewritten. `TYPE` is interpreted as the
authority URI; without it the value remains plain text and no link is
generated. A normal `NOTE` record does not carry `IDENTIFIER_STRUCTURE`;
`SNOTE` is the corresponding shared-note record.

The authoritative specification is the
[FamilySearch GEDCOM 7 specification](https://gedcom.io/specifications/FamilySearchGEDCOMv7.html),
especially `IDENTIFIER_STRUCTURE` and `PLACE_STRUCTURE`. The concrete webtrees
tag catalogue is `app/CustomTags/Gedcom7.php` in the webtrees source tree.
