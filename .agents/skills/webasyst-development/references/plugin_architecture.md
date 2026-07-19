# Webasyst and Shop-Script plugin architecture

Load this reference before designing or changing a Webasyst or Shop-Script plugin integration.

## Evidence sources

Start with the official documentation relevant to the change:

- https://developers.webasyst.ru/docs/cookbook/plugins/
- https://developers.webasyst.ru/docs/cookbook/basics/file-structure/
- https://developers.webasyst.ru/docs/cookbook/plugins/plugin-settings/
- https://developers.webasyst.ru/docs/cookbook/basics/classes/waPlugin/
- https://developers.webasyst.ru/docs/plugin/hooks/shop

Use the current local Webasyst or Shop-Script checkout and existing working plugin code as additional evidence. When no source confirms a mechanism, write `требуется проверка в документации` and do not make the dependent production change.

## Plugin structure and naming

Use the application-specific plugin root:

```text
wa-apps/[app_id]/plugins/[plugin_id]/
```

For Shop-Script use `wa-apps/shop/plugins/[plugin_id]/`. Typical plugin surfaces are `css/`, `img/`, `js/`, `lib/actions/`, `lib/classes/`, `lib/config/`, `lib/models/`, `locale/`, and `templates/actions/`.

Check the main plugin class, handler declarations, action class names, template locations, request keys, JavaScript globals, session/cookie keys, and CSS classes for application/plugin prefixes. Prefer `shopPlugin` for a Shop-Script plugin unless a documented reason requires another base class.

For a backend route such as `?plugin=[plugin_id]&action=edit`, verify the matching action class and default template convention instead of inventing a path. Typical documented forms are:

```text
lib/actions/[app_id][Plugin_id]PluginBackendEdit.action.php
templates/actions/backend/BackendEdit.html
```

For custom settings, verify `custom_settings` in `lib/config/plugin.php`, the settings action/template pair, plugin-prefixed field names, and `{$wa->csrf()}` in mutating forms. For hooks, verify the handler mapping and the handler method separately; the existence of a hook ID does not prove the shape of `$params`.

## Integration design checklist

Before implementation, record:

- app ID, plugin ID, root path, changed files, and class responsibilities;
- Given/When/Then behaviour and explicit non-goals;
- backend action, route, hook, settings UI, CLI, or install/update integration point;
- Smarty data flow and escaping; JavaScript endpoint and server-side validation;
- own and standard database tables, fields, indexes, limits, migrations, and uninstall behaviour;
- authorisation, CSRF, validation, SQL placeholders/casts, logging, and verification method.

Protect non-public `lib/`, `templates/`, and `locale/` directories with the project-compatible `.htaccess` policy when applicable. Do not write user data into product or core directories; use the database or Webasyst-supported data/cache locations.

For custom tables use `[app_id]_[plugin_id]_`; use `lib/config/db.php` for their schema. Keep `install.php` and `uninstall.php` for additional idempotent actions rather than duplicating creation or removal of tables described by `db.php`.

## Backend and bulk mutations

Do not assume a fixed `/webasyst/` backend URL. Keep backend-only plugins independent of storefront themes unless storefront behaviour is required.

For a bulk product operation preserve this sequence:

1. Filter or explicitly select targets.
2. Show server-derived preview of affected data and intended changes.
3. Require explicit confirmation.
4. Revalidate on the server and apply bounded batches.
5. Write an operation log only after success; roll back on failure when the operation is transactional.

Use bounded pagination or batches for large catalogues. Batch-load related records and explicitly review filtering, sorting, tenant, permission, and selection constraints to avoid N+1 and cross-scope writes.
