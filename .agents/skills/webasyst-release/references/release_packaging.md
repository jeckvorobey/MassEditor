# Release packaging

Load this reference only when archive creation or inspection is authorized.

## Build

1. Confirm the version, vendor, slug, release status, localization state, meta-update decision, project checks, and expected distribution manifest.
2. Run the official command from a working Webasyst installation:

   ```bash
   php wa.php compress <slug>
   ```

3. Do not use `-skip test` in the normal release path.
4. Locate the produced archive from command output and actual filesystem state; do not guess its path.
5. Keep the current final archive intact until the candidate passes every gate.

If the CLI is unavailable, stop with a clear blocker. Use an ad-hoc fallback only after explicit authorization and apply the same structure, manifest, localization, secret, and integrity checks.

## Candidate gates

- Run `gzip -t` and `tar -tzf`.
- Require exactly one root directory matching the product ID.
- Reject absolute paths, `..`, unsafe links, special files, `.git`, `.env`, keys, dumps, logs, caches, temporary files, and unnecessary development artifacts.
- Require configured runtime files, protected directories, compiled localization when shipped, and third-party license files where applicable.
- Extract into a temporary directory and compare with the expected distribution manifest, not automatically with the complete source tree; the official packager may intentionally exclude development files.
- Run a secret scan and calculate path, size, file count, and SHA-256.
- Remove only the validated temporary directory after the report is captured.

## Replacement rules

- Atomically replace a draft archive only after all gates pass.
- Never overwrite an archive marked `published` without a separate explicit force authorization.
- Never leave a partial candidate under the final filename.
- On failure, preserve the previous archive and report the exact failed gate.
