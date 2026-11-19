---
title: Props and field types
slug: props
order: 30
summary: How entries and every core field type become page props, relation depth, eager loading, and custom transformers.
---

Momentum builds `entry` from the entry's **field layout**: every field visible on that entry is
serialised by a transformer for its field type. **Everything in props is public JSON** — anyone can
read it in the page source — so leave out what shouldn't be there (see
[Configuration](/plugins/craft-momentum/docs/configuration/)).

## The entry

```json
{
  "id": 8, "uid": "…", "title": "Hello", "slug": "hello", "uri": "articles/hello",
  "url": "https://example.com/articles/hello",
  "section": "articles", "type": "article",
  "postDate": "2026-11-17T18:27:00-05:00", "dateUpdated": "2026-11-17T18:45:16-05:00",
  "fields": { "summary": "…", "related": [ … ] }
}
```

Dates are ISO 8601 strings in the site's time zone. Nested (Matrix) entries have `section: null`.

## Field types

| Field | Prop |
| --- | --- |
| Plain Text, Email | string or `null` |
| Number, Range | number or `null` |
| Lightswitch | boolean |
| Date, Time | ISO 8601 string or `null` |
| Dropdown, Radio Buttons, Button Group | `{value, label}` or `null` |
| Checkboxes, Multi-select | list of the selected `{value, label}` |
| Entries, Users, Assets, Eloquent relations | list of elements (see *Relations*) |
| Assets | each: `id, title, url, alt, filename, kind, mimeType, size, width, height, focalPoint, transforms, fields` |
| Matrix | list of nested entries in order, each with its `type` and `fields` |
| Content Block | `{id, fields}` or `null` |
| CKEditor | `{html, chunks}` (see *Rich text*) |
| Markdown | `{html, chunks}` |
| Link | `{type, url, label, target, title, rel, download, element}` or `null` |
| Money | `{amount, currency, formatted}` — `amount` is a decimal string (`"12.50"`) |
| Color | `{hex, label}` or `null` |
| Country | `{code, name}` or `null` |
| Icon | `{name, set}` or `null` |
| Table | list of rows keyed by column handle |
| JSON | the decoded value |
| Addresses | **never sent** (personal data) |
| Anything else | its value made JSON-safe; objects Momentum can't represent become `null` |

## Elements

- **Users** are only ever `{id, name}`: never email addresses, usernames or anything else.
- **Eloquent elements** (Craft 6's `Element\Eloquent`) send `{id, title, url, attributes, fields}`,
  where `attributes` are the model's exposed attributes minus its `$hidden` ones (and only its
  `$visible` ones, if it sets them) — what the model's own `toArray()` would show.
- **Other element types** (third-party, addresses) send only `{id, title, url}`.

## Relations and depth

*Relation depth* (default 2) is how many relation hops are serialised. Each hop through a relation
(Entries, Assets, Users, a Link's target) spends one; when depth runs out, related elements are
sent as ids. Nested elements — a field's own Matrix entries, Content Block, CKEditor entries — are
part of their owner and don't spend depth.

What counts is **how an element is reached**: a relation that points at a nested entry is still a
relation hop, so it spends depth and (in GraphQL) passes the schema's checks.

Relations are resolved on the current site, so a French page relates French entries.

## Rich text

CKEditor fields send:

```json
{
  "html": "<p>Intro</p><p>Outro</p>",
  "chunks": [
    { "type": "markup", "html": "<p>Intro</p>" },
    { "type": "entry", "entry": { "id": 12, "type": "callout", "fields": { … } } },
    { "type": "markup", "html": "<p>Outro</p>" }
  ]
}
```

`html` is the markup with reference tags parsed and nested entries left out; `chunks` is the same
content in order, so you can render nested entries with your own components. Render `html` with
`v-html` / `dangerouslySetInnerHTML` / `{@html}`: it's the editor's HTML, purified by the field's
own settings.

## Image transforms

List named transforms under *Image transforms* and every image asset gets their URLs:
`img.transforms.thumb`. They're eager loaded with the assets.

## Eager loading

Before serialising, Momentum eager-loads every relation and nested-entry field the layouts need,
level by level, for all elements at that level at once. A page costs a query per field per level, not
per element: in the test suite an entry with nine Matrix blocks (each with an image and a relation)
takes no more queries than one with four, and under a third of the queries of lazy loading.

## Custom field types

Register a transformer for your field class (subclasses use it too):

```php
use JustinHoltWeb\Momentum\Events\RegisterFieldTransformers;
use JustinHoltWeb\Momentum\Props\Transformers\FieldTransformer;

Event::listen(function (RegisterFieldTransformers $event) {
    $event->transformers[MyField::class] = new class implements FieldTransformer {
        public function transform($field, $value, $serializer, $depth): mixed
        {
            return ['score' => $value?->score];
        }

        public function typeScript($field, $writer): string
        {
            return '{ score: number | null }';
        }
    };
});
```

Use `$serializer->relations($value, $depth)` for elements your field relates to and
`$serializer->nested($value, $depth)` for elements it owns. Return
`JustinHoltWeb\Momentum\Props\Omit::Field` to leave the field out.

## Props in templates

`momentum.props(entry)` (Twig) and `$momentum->props($entry)` (Blade) return the same props an Inertia
page gets (without the shared ones), e.g. to hydrate a Vue island on an otherwise server-rendered page:

```twig
<script type="application/json" id="props">{{ momentum.props(entry)|json_encode|raw }}</script>
```

```blade
<script type="application/json" id="props">{!! json_encode($momentum->props($entry), JSON_HEX_TAG) !!}</script>
```

`momentum.componentFor(entry)` returns the entry's component, or `null` when its section doesn't use
an `inertia:` template.
