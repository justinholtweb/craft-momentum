---
title: Pro features
slug: pro
order: 60
summary: TypeScript types for page props, server-side rendering, and deferred fields.
---

Pro (price TBD) adds three things on top of Lite.

## Typed props

```sh
php artisan craft:momentum:types                      # writes resources/js/types/momentum.d.ts
php artisan craft:momentum:types --output=resources/js/momentum.d.ts
php artisan craft:momentum:types --section=articles --section=news
php artisan craft:momentum:types --stdout
```

It writes a declaration per entry type reached from your Inertia sections, typed by the same transformers
that build the props, plus `MomentumPages`, a map of component name → page props:

```ts
export type ArticleEntry = MomentumEntry<"article", {
  summary: string | null; tone: { value: "warm" | "cool"; label: string } | null;
  blocks: Array<TextBlockEntry | GalleryEntry>; images: Array<MomentumAsset | number>;
  body: MomentumRichText | null; related?: Array<ArticleEntry | number>
}>;

export interface MomentumPages {
  "Pages/Article": MomentumPageProps<ArticleEntry>;
}
```

```vue
<script setup lang="ts">
import type { MomentumPages } from '@/types/momentum';
defineProps<MomentumPages['Pages/Article']>();
</script>
```

Excludes and renames are applied, deferred fields are optional (`?`), dropdown options become literal
unions, Matrix fields become unions of their entry types and Entries fields limited to sections become
unions of those sections' entry types. Relations are `Type | number` because they turn into ids when
depth runs out. Run it again after changing fields (e.g. in your build or a `post-deploy` step); the
output path must be a relative `.ts` path inside the project.

## Server-side rendering

Switch on **Server-side rendering** and run Inertia's SSR server (built from your `ssr.js` entry):

```sh
npm run build                     # vite build && vite build --ssr
php artisan inertia:start-ssr     # or: node bootstrap/ssr/ssr.js
```

First visits are posted to the SSR server (`ssrUrl`, default Inertia's `http://127.0.0.1:13714`) and
the root template outputs its head and body; Inertia visits stay JSON. If the server is down or errors,
the page renders client-side as usual and a warning is logged — a stopped SSR server never takes the
site down.

Momentum owns SSR for site pages only: it doesn't switch on Inertia's global `inertia.ssr.enabled`, so
Craft's control panel is never server-rendered by your SSR bundle. Twig `inertia()` / `inertiaHead()` and
Blade `@inertia` / `@inertiaHead` both get the server's render.

## Deferred fields

List heavy fields (big relations, galleries) under **Deferred fields**. They're left out of the first
response and fetched by Inertia right after the page renders, as `entry.fields.<handle>`:

```vue
<Deferred data="entry.fields.related">
  <template #fallback>Loading…</template>
  <RelatedList :items="entry.fields.related" />
</Deferred>
```

Partial reloads work on any prop (`router.reload({ only: ['entry.fields.related'] })`). Deferred fields
are eager loaded when they're requested. In GraphQL and `momentum.props()` they're always sent in full.
