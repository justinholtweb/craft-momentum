---
title: Configuration
slug: configuration
order: 40
summary: Every setting, field rules (exclude, rename, defer), and config/craft/momentum.php.
---

Settings → Momentum, or `config/craft/momentum.php` (lists may be arrays there).

| Setting | Default | |
| --- | --- | --- |
| `rootTemplate` | `_momentum/root` | Twig or Blade site template for first visits |
| `viteEntries` | none | Entry points the default root template loads, one per line |
| `propDepth` | `2` | Relation hops serialised (0–5) |
| `excludeFields` | none | Fields never sent, one rule per line |
| `renameFields` | none | `handle: propName`, one per line |
| `assetTransforms` | none | Named image transforms added to image assets |
| `shareCurrentUser` | on | `currentUser` prop (`{id, name}`) on every page |
| `assetVersion` | manifest hash | Inertia asset version |
| `deferFields` | none | **Pro.** Fields sent as deferred props |
| `ssr` | off | **Pro.** Server-side render first visits |
| `ssrUrl` | `inertia.ssr.url` | **Pro.** SSR server URL |

## Field rules

`excludeFields`, `renameFields` and `deferFields` take a field handle, optionally qualified by the
entry type (or the volume, for assets):

```
internalNotes            # everywhere
article.seoNotes         # only on `article` entries
```

```
summary: excerpt                 # renamed everywhere
gallery.images: photos           # only on `gallery` entries
```

A qualified rule wins over a bare one. Rules are validated when you save: a handle with spaces or a
rename that isn't `handle: propName` is refused.

**Exclude anything private.** Field visibility rules (conditions) are respected, but everything that
remains is public JSON in the page source.

## Example config file

```php
// config/craft/momentum.php
return [
    'viteEntries' => ['resources/js/app.js'],
    'propDepth' => 1,
    'excludeFields' => ['internalNotes', 'article.seoNotes'],
    'renameFields' => ['summary: excerpt'],
    'assetTransforms' => ['thumb', 'hero'],
];
```
