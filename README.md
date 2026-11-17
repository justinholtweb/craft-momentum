# Momentum

Render Craft entries as Inertia pages (Vue, React or Svelte) straight from Craft routing — no headless API, no second router.

> **Status:** phase 1 (front-end Inertia pages) working in the Craft 6 harness. See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- Laravel integration: inertiajs/inertia-laravel. Already required by Craft 6 core (the CP is an Inertia app), so no new dependency.

## Usage

1. **Point a section at a component.** In the section's site settings, set *Template* to
   `inertia:Pages/Article`. Craft still routes `articles/{slug}`; Momentum answers with the `Pages/Article`
   component instead of rendering Twig. Other sections keep their Twig templates.
2. **Props.** The page gets `entry` (id, title, slug, uri, url, section, type, dates and `fields`, built from
   the entry's field layout) plus shared `site`, `currentUser` and `errors`. Relations are serialised
   *Relation depth* hops deep (default 2), then sent as ids; Matrix/nested entries don't spend depth.
   Users only ever expose `id` and `name`; unknown element types only `id`, `title` and `url`.
3. **Root template.** First visits render `_momentum/root` (or your *Root template*), a site Twig template
   that outputs `{{ inertiaHead(page) }}` and `{{ inertia(page) }}`. Set *Vite entry points* (e.g.
   `resources/js/app.js`) to load your front-end bundle, or write your own root template with `vite()`.
4. **Your own routes.** Inject `JustinHoltWeb\Momentum\Momentum` and return
   `$momentum->render('Search', [...])` from routes that use the `craft.web` middleware group (or the
   `momentum` middleware alias).

**Everything in props is public.** List private field handles under *Excluded fields*, or listen for
`JustinHoltWeb\Momentum\Events\EntryPropsResolving` to reshape props or swap the component.

## Editions and pricing

Lite / Pro — Lite free / Pro $79 (provisional).

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-momentum --install`).
Live checks: `tests/Harness/check.sh` (prerequisites in its header).
