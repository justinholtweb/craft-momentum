---
title: Troubleshooting
slug: troubleshooting
order: 80
summary: Blank pages, 409s, missing fields, SSR, and the Craft 6 beta.
---

## The page is blank

The root template rendered, but no JavaScript ran. Set *Vite entry points* (or call `vite()` in your own
root template), build your front end, and check the browser console for a component that didn't resolve:
`inertia:Pages/Article` needs `./Pages/Article.vue` under your resolver's glob.

## Every Inertia visit gets a 409

The client's asset version doesn't match the server's, so Inertia reloads the page. That's expected after
a deploy. If it keeps happening, check *Asset version* and that `public/build/manifest.json` is the build
the browser loaded.

## A field is missing from props

- Is it in *Excluded fields* (bare or qualified by this entry type)?
- Is it hidden on this entry by a field-layout condition?
- Addresses fields are never sent.
- Pro: is it in *Deferred fields*? It arrives in a second request.

## A relation is a number, not an object

Relation depth ran out. Raise *Relation depth*, or fetch it with a partial reload.

## SSR isn't happening

SSR is Pro-only, needs *Server-side rendering* switched on, and the SSR server reachable at the
*SSR server URL* from PHP. Failures are logged (`Momentum: the SSR server …`) and the page falls back to
client-side rendering.

## Craft 6 beta

Momentum targets Craft 6, which is in beta: use caution on production, keep backups, and expect changes
between betas.
