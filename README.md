# Momentum

Render Craft entries as Inertia pages (Vue, React or Svelte) straight from Craft routing — no headless API, no second router.

> [!WARNING]
> **Craft 6 only, and in beta.** Momentum is a native **Craft CMS 6** plugin. Craft 6 is still in beta,
> and so is Momentum (`6.0.0-beta.1`). Try it on development and staging sites first and **use caution on
> production**: keep backups, and expect changes between betas. It does not run on Craft 5.

Docs: [justinholt.com/plugins/craft-momentum](https://justinholt.com/plugins/craft-momentum/docs/) (also in [docs/](docs/)). See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- `inertiajs/inertia-laravel` v3 — already required by Craft 6 core (the CP is an Inertia app), so no new dependency
- An Inertia v3 front end (`@inertiajs/vue3`, `@inertiajs/react` or `@inertiajs/svelte`) built with Vite

## How it works

1. **Point a section at a component.** Set the section's *Template* to `inertia:Pages/Article`. Craft still
   routes `articles/{slug}` (statuses, sites, structures as usual); Momentum answers with the `Pages/Article`
   component. Other sections keep their Twig/Blade templates.
2. **The entry arrives as props**, built from its field layout: every core field type has a transformer
   (assets with named transforms, relations to *Relation depth*, Matrix and Content Block entries, CKEditor
   HTML split into chunks around nested entries, Link, options, Money, Table…). Relations and nested entries
   are eager loaded level by level, so a page costs a query per field per level, not per element.
   Users are only `{id, name}`, Addresses are never sent, Eloquent elements respect the model's `$hidden`.
3. **Shared props:** `site`, `currentUser` (`{id, name}` or null), `errors`, and on multi-site installs
   `alternates` (the entry's URL on every site, for language switchers).
4. **Root template:** first visits render `_momentum/root` or your own Twig (`{{ inertia(page) }}`) or Blade
   (`@inertia`) template, loading your *Vite entry points*.
5. **Live preview and share links** render the draft through the same component.

**Everything in props is public.** Exclude private fields under *Excluded fields* (`handle` or
`entryType.handle`), rename with `handle: propName`, or reshape props with the `EntryPropsResolving` event.

```php
Route::get('search', fn (\JustinHoltWeb\Momentum\Momentum $m) => $m->render('Pages/Search', ['q' => request('q')]))
    ->middleware(['web', 'craft', 'craft.web']);
```

**Twig and Blade:** `momentum.props(entry)` / `$momentum->props($entry)` return the same props an Inertia page
gets; `momentum.componentFor(entry)` its component.

**GraphQL:** `momentumPage(id | uri, site)` returns `{component, url, site, props}` for schemas given *Read
Momentum page props*; related entries, assets and users are kept only when the schema could fetch them itself.

## Editions

Lite (free): everything above. Pro (price TBD):

- **Typed props** — `php artisan craft:momentum:types` writes TypeScript declarations per entry type and a
  `MomentumPages` component → props map.
- **Server-side rendering** through Inertia's SSR server, for site pages only (the CP is untouched), falling back
  to client-side rendering if the server is unreachable.
- **Deferred fields** — heavy fields sent as Inertia deferred props and fetched after the page renders.

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-momentum --install`).
Live checks: `tests/Harness/check.sh` — self-contained fixtures (every core field type, CKEditor with nested
entries, a temporary second site, GraphQL schemas, a real Vue 3 + Inertia SSR server) and cleanup.
Browser-verified with Vue 3 + `@inertiajs/vue3` (`tests/Harness/frontend/`).
