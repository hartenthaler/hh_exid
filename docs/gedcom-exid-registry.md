# GEDCOM EXID registry

`resources/config/gedcom-exid-types.json` is a local, deduplicated snapshot of
the official [FamilySearch GEDCOM registries](https://github.com/FamilySearch/GEDCOM-registries/tree/main/uri/exid-types).
The file records the upstream repository, path, commit and date so that an
update can be reviewed and reproduced.

The registry snapshot contains standard `EXID.TYPE` definitions only. It is
separate from `resources/config/exid-authorities.json`, which contains the
provider-specific link templates used by this module. A registry entry does
not automatically imply that the payload can safely be turned into a public
URL.

To update the snapshot:

1. Read the current YAML files in the upstream `uri/exid-types` directory.
2. Copy their identifier, label, language and URI into the JSON file.
3. Keep exactly one entry per URI and update the recorded upstream commit.
4. Review link templates separately; do not invent a URL from a registry URI.
5. Validate the JSON and run the PHP and gettext checks before committing.
