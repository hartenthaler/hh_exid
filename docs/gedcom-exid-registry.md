# GEDCOM EXID registry

`resources/config/gedcom-exid-types.json` is a local, deduplicated snapshot of
the official [FamilySearch GEDCOM registries](https://github.com/FamilySearch/GEDCOM-registries/tree/main/uri/exid-types).
The file records the upstream repository, path, commit and date so that an
update can be reviewed and reproduced.

The registry snapshot contains registered `EXID.TYPE` definitions only. It is
separate from `resources/config/exid-authorities.json`, which contains only
additional authorities that are not registered in the GEDCOM registry. If a
URI occurs in both files, the registry entry is authoritative and the local
provider definition is discarded. A registry entry does not automatically
imply that the payload can safely be turned into a public URL, and it never
supplies a missing TYPE for an EXID.

To update the snapshot:

1. Read the current YAML files in the upstream `uri/exid-types` directory.
2. Copy their identifier, label, language and URI into the JSON file.
3. Keep exactly one entry per URI and update the recorded upstream commit.
4. Review the allow-list and value pattern separately; do not add an untrusted
   URI merely because it appears in the registry.
5. Validate the JSON and run the PHP and gettext checks before committing.

## When the standard adds a YAML file

If the upstream directory contains a new YAML file, do not add it by copying
the file blindly. Use this procedure:

1. Confirm that the file is an EXID type definition (`type: uri`) and read its
   `label`, `lang`, `uri` and documentation links.
2. Check the URI against every existing entry in
   `gedcom-exid-types.json`. The URI is the stable identity; a different file
   name or label does not justify a second entry for the same URI.
3. Add one JSON object with the upstream file name and metadata. Keep the
   order aligned with the upstream directory and preserve the original URI
   exactly.
4. Update `source.commit` and `source.updated` to the commit from which the
   complete directory was read. Do not mix files from different upstream
   revisions in one snapshot.
5. Decide separately whether the URI and its value pattern can be added to
   `exid-authorities.json`. A registry URI alone is not proof that appending an
   identifier creates a usable web link.
6. Run the duplicate-URI check, JSON validation, PHP lint and gettext checks.
   Record the new upstream file in the commit message and review the resulting
   diff before merging.
