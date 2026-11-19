---
title: Multi-site
slug: multisite
order: 45
summary: Per-site props, relations and URLs, alternates for language switchers, translations.
---

Momentum renders each site's own version of an entry:

- `entry` is the entry on the **current site** — its title, slug, URL and translated field values.
- Relations resolve on the current site, so related entries carry that site's titles and URLs.
- `site` is the current site (`handle`, `name`, `language`, `baseUrl`), for `<html lang>` or formatting.
- Each site can use its own component: the `inertia:` template is a per-site section setting.

## `alternates`

On multi-site installs, every page gets the entry's URL on each site it's live on:

```json
"alternates": [
  { "site": "default", "language": "en-US", "url": "https://example.com/articles/hello", "current": false },
  { "site": "fr", "language": "fr-FR", "url": "https://example.com/fr/articles/bonjour", "current": true }
]
```

Use it for a language switcher or `hreflang` links. Sites where the entry is disabled are left out;
with only one site left the list is empty. It costs one query.

```vue
<Head>
  <link v-for="alt in alternates" :key="alt.site" rel="alternate" :hreflang="alt.language" :href="alt.url" />
</Head>
```

## Translations

Momentum's own strings (settings, messages) use the `momentum` translation category; copy
`lang/en/momentum.php` to `lang/<locale>/momentum.php` to translate them. Your page content comes
from Craft, already translated per site.
