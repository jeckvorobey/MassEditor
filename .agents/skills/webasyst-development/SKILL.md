---
name: webasyst-development
description: This skill should be used when planning, implementing, reviewing, or completing a Webasyst or Shop-Script app, plugin, widget, or theme change, especially for backend actions, hooks, database work, bulk mutations, and OpenSpec-driven delivery.
---

# Webasyst Development

Drive a scoped Webasyst or Shop-Script change from evidence to verified implementation. Keep actions thin, preserve the product's conventions, and do not invent framework behaviour.

## Start

1. Read `AGENTS.md`, inspect the applicable repository root, active OpenSpec changes, git status, and project checks.
2. State the requested user effect, included scope, non-goals, data changes, and trust boundaries.
3. Read `references/plugin_architecture.md` before changing plugin structure, actions, hooks, settings, database files, templates, JavaScript, CSS, or install/uninstall code.
4. Read `references/documentation_evidence.md` before relying on a Webasyst or Shop-Script API, hook, route, base class, or configuration format.
5. Read `references/testing_security_review.md` before implementing runtime behaviour or completing any code/skill change.
6. Read `references/commercial_mvp.md` when shaping a new commercial plugin or materially expanding an MVP.

## Required delivery loop

Follow this order unless the user explicitly requests a narrower read-only activity:

1. Explore unclear requirements with OpenSpec discovery.
2. Create or update an OpenSpec change before production implementation.
3. Specify Given/When/Then behaviour and add the relevant test before the implementation.
4. Implement the smallest scoped change; use services, models, localisation, safe SQL, batching, and plugin-prefixed names where applicable.
5. For a PHP/backend mutation, review authorisation, CSRF, server-side validation, output escaping, preview, explicit confirmation, limits, rollback, and audit logging.
6. Re-check every changed Webasyst mechanism against official documentation or a confirmed local implementation.
7. Run targeted and full relevant checks, then OpenSpec strict validation.
8. Before declaring completion, run `complexity-optimizer` and then `secure-review-loop`; report any unavailable checks as residual risk.

## Routing

Use the minimum suitable global skill for the current stage:

- OpenSpec exploration, proposal, implementation, sync, or archive workflow.
- PHP TDD and PHP security review for PHP changes.
- Store and release work through `webasyst-release` only when it is in scope.
- Commit, push, PR, packaging, publication, sync, and archive only with explicit user authorisation.

## Stop conditions

Stop the dependent implementation and report the evidence gap when an API, hook, parameter shape, route, configuration contract, expected behaviour, or safe rollback cannot be confirmed. Do not mark a task complete without evidence.
