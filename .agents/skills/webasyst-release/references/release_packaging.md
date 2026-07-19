# Release packaging checks

Load this reference when creating or verifying a Webasyst release archive.

Build from the intended production tree only. Verify the candidate before replacing any existing archive:

1. Validate version, vendor, metadata, and required localisation files.
2. Compile and check gettext catalogues when the product uses `.po` and `.mo`.
3. Require a single archive root named after the product or plugin ID.
4. Reject absolute paths, `..` traversal, unsafe links, special files, development materials, temporary files, and debug artefacts.
5. Check gzip/tar integrity, manifest, protected directories, and required metadata.
6. Unpack the candidate and compare it recursively with the production tree.
7. Record path, size, file count, checksum, commands, and results.

Exclude repository-only tests, Docker files, local plans, caches, dependency trees, temporary files, secrets, and generated development artefacts unless the product contract explicitly requires them. Confirm that protected directories and compiled localisation needed by production are present.

If any required check fails, keep the prior release archive intact and report the failed gate.
