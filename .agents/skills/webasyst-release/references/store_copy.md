# Webasyst Store copy and changelog

Load this reference before drafting Store descriptions, screenshot captions, changelog entries, or update notes.

## Description

Write Russian and English from the same confirmed features and limitations. Provide short summaries and, when requested, Store-ready HTML. Use only:

`b`, `i`, `u`, `em`, `strong`, `br`, `a`, `img`, `p`, `ul`, `ol`, `li`, `table`, `tr`, `td`, `th`, `tbody`, `thead`, `tfoot`, `blockquote`, `pre`, `code`.

Do not use CSS, inline styles, scripts, iframes, forms, headings, divs, spans, or unsupported attributes. Describe merchant outcomes and the real backend workflow; disclose meaningful limitations, compatibility requirements, and scope boundaries. Do not invent support, screenshots, trials, pricing, or integrations.

Keep Webasyst, Shop-Script, Webasyst Store, Customer Center and their accepted Russian equivalents consistent. Use lowercase `вы` in Russian and avoid excessive title case in English. When relevant and confirmed, recommend a transparent PNG cover at 200×110 px and clean screenshots readable after scaling to at most 970 px width.

## Changelog and update note

Read the current product version and release evidence before writing. Keep `docs/CHANGELOG.md` and `docs/CHANGELOG.en.md` factually synchronized: identical versions, order, and section scope, with natural language rather than literal translation.

Use only non-empty `Added`, `Improved`, `Fixed`, `Compatibility`, and `Notes` sections. Update an existing version section rather than duplicating it. Keep patch notes concise and derive any Store update note only from the canonical changelog facts.

Treat the product config (`plugin.php`, `app.php`, `widget.php`, or `theme.xml`) as the version source. Before finalising release notes, inspect the diff/log and release documents, check whether meta-updates or localisation refresh are user-visible, and keep unshipped internal refactors out of canonical history. Use the same version order and section set in both language files.
