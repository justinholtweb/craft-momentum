---
title: FAQ
slug: faq
order: 90
summary: Craft 6 and the beta, editions, frameworks, and how it compares to going headless.
---

## Does Momentum work on Craft 5?

No. It's a native **Craft 6** plugin: it uses the Inertia server adapter Craft 6 ships for its control
panel. Craft 6 is still in beta.

## Is it safe to use in production?

Craft 6 and Momentum are both in beta. Use caution: try it on development and staging first, keep
backups, and expect changes between betas.

## Vue, React or Svelte?

Any Inertia v3 adapter. Momentum only produces Inertia responses; the front end is yours.

## How is this different from going headless?

Craft keeps routing, statuses, sites, live preview and SEO URLs. There's no API to design, no second
router, no client-side data fetching for page content and no preview plumbing: a section points at a
component and the entry arrives as props.

## Can I mix Inertia pages and Twig/Blade templates?

Yes. Only sections whose template is `inertia:…` (and your own `Momentum::render()` routes) become
Inertia pages.

## Is private data safe?

Props are public JSON. Users are only `{id, name}`, Addresses fields are never sent, unknown element
types only send `{id, title, url}` and Eloquent models respect `$hidden`. Anything else in a field
layout is sent unless you exclude it.

## What's in Lite and what's in Pro?

Lite renders pages with full props, live preview, multi-site and GraphQL. Pro adds TypeScript types,
SSR and deferred fields. See [Installation](/plugins/craft-momentum/docs/installation/).
