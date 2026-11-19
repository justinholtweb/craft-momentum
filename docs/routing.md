---
title: Routing and pages
slug: routing
order: 20
summary: inertia: templates, your own routes, shared props, the root template, live preview.
---

## `inertia:` section templates

A section's *Template* setting can name an Inertia component: `inertia:Pages/Article`. Craft's own
element routing still resolves the URI to the entry (statuses, post dates, sites, structure URIs all as
usual); Momentum renders that entry with the component instead of a template. Component names are
letters, numbers, `-`, `_` and `/` — anything else (dots, `..`) is ignored and the section renders as an
ordinary template would.

Entry types in one section share the component; branch on `entry.type` inside it, or swap the component
per entry with the `EntryPropsResolving` event:

```php
use JustinHoltWeb\Momentum\Events\EntryPropsResolving;

Event::listen(function (EntryPropsResolving $event) {
    if ($event->entry->type->handle === 'gallery') {
        $event->component = 'Pages/Gallery';
    }
    $event->props['related'] = /* … anything public … */;
});
```

## Page props

Every page gets:

| Prop | |
| --- | --- |
| `entry` | the entry and its fields — see [Props](/plugins/craft-momentum/docs/props/) |
| `alternates` | the entry on every site it's live on (multi-site only) |
| `site` | `handle`, `name`, `language`, `baseUrl` of the current site |
| `currentUser` | `{id, name}` of the logged-in user, or `null` (switch off with *Share the current user*) |
| `errors` | validation errors flashed by Laravel |

## Your own routes

Inject `JustinHoltWeb\Momentum\Momentum` and render any component from a Laravel route that runs the
`craft.web` middleware group (or add the `momentum` middleware alias):

```php
use JustinHoltWeb\Momentum\Momentum;

Route::get('search', fn (Momentum $momentum) => $momentum->render('Pages/Search', [
    'results' => /* … */,
]))->middleware(['web', 'craft', 'craft.web']);
```

## The root template

First visits (not Inertia XHRs) render a root template around the page. The default, `_momentum/root`,
loads your *Vite entry points* and outputs Inertia's root element. Copy it to write your own, then set
*Root template* to its path. Twig and Blade are both supported — Craft 6 picks the engine by extension:

```twig
{# templates/_root.twig #}
<!DOCTYPE html>
<html lang="{{ currentSite.language }}">
<head>
  {{ vite(viteEntries) }}
  {{ inertiaHead(page) }}
</head>
<body>{{ inertia(page) }}</body>
</html>
```

```blade
{{-- resources/views/root.blade.php --}}
<!DOCTYPE html>
<html lang="{{ $currentSite->language }}">
<head>
  @vite($viteEntries)
  @inertiaHead
</head>
<body>@inertia</body>
</html>
```

Both output identical markup: the page JSON in `<script data-page="app" type="application/json">`
(with `<` escaped, so field content can't break out of it) and `<div id="app">`.

## The control panel is untouched

Momentum's middleware is pushed onto the `craft.web` (site) group only, with its own root view and asset
version. Craft's control panel keeps its own Inertia setup.

## Asset versioning

Inertia reloads the page when the front end's asset version changes. Momentum uses a hash of
`public/build/manifest.json`; set *Asset version* to override it (e.g. a deploy id).

## Live preview and share links

Preview and share-link tokens render through the same component, with the draft's content, on the
first visit and on Inertia visits (the page URL keeps the token, so `<Link>` navigation inside the
preview stays in preview).
