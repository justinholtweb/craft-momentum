# Release Notes for Momentum

## 6.0.0-beta.1 - Unreleased

### Added
- Sections whose template is `inertia:<Component>` render their entries as Inertia pages, routed by Craft.
- Front-end Inertia middleware on the `craft.web` group only (asset-version 409s, shared `site`/`currentUser`).
- Field-layout-driven prop serialiser with relation depth, excluded fields and conservative user/unknown-element output.
- Twig `inertia(page)` / `inertiaHead(page)` functions and a default `_momentum/root` root template.
- `Momentum::render()` for custom routes and the `EntryPropsResolving` event.
