---
title: Installation
slug: installation
order: 10
summary: Requirements, the Craft 6 beta caution, install, and your first Inertia page.
---

> **Craft 6 only, and still in beta.** Momentum is a native **Craft CMS 6** plugin. Craft 6 is
> still in beta, and so is Momentum (`6.0.0-beta.1`). Try it on development and staging sites
> first, and **use caution before running it on a production site**: keep backups, and expect
> things to change between betas. It does not run on Craft 5.

## Requirements

- Craft CMS **6.0.0-beta.1** or later (native Craft 6, no Yii 2 adapter)
- PHP 8.5
- `inertiajs/inertia-laravel` v3. Craft 6's control panel is an Inertia app, so it's already installed.
- A Vite front end with an Inertia adapter: `@inertiajs/vue3`, `@inertiajs/react` or `@inertiajs/svelte` (v3).

## Editions

| | Lite | Pro |
| --- | --- | --- |
| Price | Free | TBD |
| Sections rendered as Inertia pages, Craft routing | ✓ | ✓ |
| Field-layout props for every core field type, eager loaded | ✓ | ✓ |
| Excluded / renamed fields, image transforms, relation depth | ✓ | ✓ |
| Live preview and share links | ✓ | ✓ |
| Multi-site props and `alternates` | ✓ | ✓ |
| `momentum.props()` in Twig and Blade, `momentumPage` in GraphQL | ✓ | ✓ |
| TypeScript types for your page props (`craft:momentum:types`) | | ✓ |
| Server-side rendering | | ✓ |
| Deferred fields (Inertia deferred props, partial reloads) | | ✓ |

## Install

```sh
composer require justinholtweb/craft-momentum
php artisan craft:plugin:install momentum
```

## Your first page

1. **Front end.** Install your adapter and point Vite at an entry file that boots Inertia:

   ```js
   // resources/js/app.js
   import { createApp, h } from 'vue';
   import { createInertiaApp } from '@inertiajs/vue3';

   createInertiaApp({
     resolve: (name) => {
       const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
       return pages[`./${name}.vue`];
     },
     setup({ el, App, props, plugin }) {
       createApp({ render: () => h(App, props) }).use(plugin).mount(el);
     },
   });
   ```

2. **Settings → Momentum → Vite entry points:** `resources/js/app.js`.
3. **A section.** In its site settings set *Template* to `inertia:Pages/Article`. Craft still routes
   `articles/{slug}`; Momentum answers with the `Pages/Article` component (`resources/js/Pages/Article.vue`).
4. **The component** gets the entry as a prop:

   ```vue
   <script setup>
   defineProps({ entry: Object, site: Object });
   </script>
   <template>
     <h1>{{ entry.title }}</h1>
     <p>{{ entry.fields.summary }}</p>
   </template>
   ```

Sections with ordinary templates keep rendering Twig or Blade, so you can move a site over one section
at a time. Component names are the path under your resolver's root: `inertia:Pages/Article` resolves
`./Pages/Article.vue` with the glob above.
