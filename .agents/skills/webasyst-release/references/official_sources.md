# Official Webasyst release sources

Primary documentation verified on 2026-07-19. Re-open these pages for drift-prone requirements before preparing a real release.

- Store product requirements: https://developers.webasyst.com/docs/store/webasyst-store-requirements/
- Preparing product updates: https://developers.webasyst.com/docs/store/product-update/
- Product self-check list: https://developers.webasyst.com/docs/store/check-list/
- Console tools, including `wa.php compress`: https://developers.webasyst.com/docs/features/console-tools/
- Meta updates: https://developers.webasyst.com/docs/cookbook/meta-updates/
- Plugin platform: https://developers.webasyst.com/docs/cookbook/plugins/

Applied rules:

- Use a numeric vendor and three-part version in the product config.
- Package Store products as tar.gz with one root named by product ID.
- Use the documented file/config structure and protected directories.
- Use `php wa.php compress <slug>` as the primary archive path and do not skip normal checks.
- Bump patch/minor/major according to fixes/minor improvements, new functionality/major improvements, or significant rework.
- Add meta-updates for new schema, changed stored format, and removed previous-version files; make them repeat-safe.
- Refresh gettext and test the upgrade from the previous version.
- Keep descriptions and screenshots aligned with actual functionality.

Do not copy official pages into the skill. Record only applicable rules, evidence date, and direct primary-source links.
