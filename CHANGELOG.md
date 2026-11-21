# Release Notes for Momentum

## 6.0.0-beta.2 - 2026-11-21

### Changed
- No code changes. The changelog now carries release dates.

## 6.0.0-beta.1 - 2026-11-19

> Craft 6 only. Craft 6 is in beta: use caution on production.

### Added
- Sections whose template is `inertia:<Component>` render their entries as Inertia pages, routed by Craft.
- Front-end Inertia middleware on the `craft.web` group only (asset-version 409s, shared `site`/`currentUser`/`errors`); the control panel keeps Craft's own.
- Root templates in Twig (`inertia(page)`, `inertiaHead(page)`) or Blade (`@inertia`, `@inertiaHead`), with the default `_momentum/root`.
- Field-layout-driven props with a transformer per field type: assets (with named image transforms), relations to *Relation depth*, Matrix and Content Block entries, CKEditor (`html` + `chunks` around nested entries), Markdown, Link, options, Money, Color, Country, Icon, Table, JSON. Addresses are never sent.
- `RegisterFieldTransformers` event for custom field types; `EntryPropsResolving` to reshape props or swap the component.
- Excluded, renamed and (Pro) deferred fields, each by handle or `entryType.handle`.
- Eager loading planned from the layouts, level by level: queries don't grow with the number of blocks or related elements.
- Craft 6 Eloquent elements send their model's non-`$hidden` attributes.
- Multi-site: props and relations follow the current site; `alternates` lists the entry's URL on every site.
- Live preview and share links render the draft through the same component.
- `momentum.props(entry)` and `momentum.componentFor(entry)` in Twig and Blade.
- GraphQL: `momentumPage(id|uri, site)`, behind a “Read Momentum page props” schema permission, limited to what the schema can read.
- `Momentum::render()` for your own routes.
- Pro: `craft:momentum:types` writes TypeScript declarations for page props.
- Pro: server-side rendering through Inertia's SSR server, site pages only, falling back to client-side rendering.
- Pro: deferred fields (Inertia deferred props) and partial reloads.
