---
title: GraphQL
slug: graphql
order: 50
summary: The momentumPage query — an entry's component and page props, scoped by the schema.
---

Momentum is a rendering layer, but the same page props are available over Craft's GraphQL API — useful
for a static build, a native app sharing components, or checking what a page will receive.

## Permission

In a GraphQL schema, tick **Momentum → Read Momentum page props**. Without it the query doesn't exist
for that schema.

## `momentumPage`

```graphql
{
  momentumPage(uri: "articles/hello", site: "default") {
    component   # "Pages/Article"
    url
    site
    props       # JSON string: JSON.parse() it
  }
}
```

Arguments: `id` **or** `uri` (`__home__` for the homepage), and `site` (default: the schema's primary
readable site). It returns `null` when the entry doesn't exist, isn't live, isn't readable by the
schema, or its section doesn't use an `inertia:` template on that site.

`props` is the page's `entry` and `alternates` (and anything `EntryPropsResolving` listeners add; they
can tell by `$event->context === EntryPropsResolving::CONTEXT_GRAPHQL`). Shared props (`site`,
`currentUser`, `errors`) aren't included, and deferred fields are sent in full.

## What a schema sees

Props never reach past what the schema could fetch itself:

- The entry must be readable by the schema (its section, and for token users their view permissions).
- **Related** entries, assets and users are kept only if the schema could fetch them with `entries`,
  `assets` or `users`, on a site the schema can read; the rest are left out of the list.
- Other element types (Eloquent elements, third-party elements) are left out.
- A Link whose target is left out is `null` — URL and label too.
- `alternates` only lists sites the schema can read.
- Tokens tied to a user don't see fields that user couldn't see.

Errors (`Pass either id or uri.`, `This schema can’t read the “fr” site.`) come back as GraphQL errors;
an unknown site and an unreadable one read the same.
