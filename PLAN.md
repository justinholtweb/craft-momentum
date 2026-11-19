# Momentum — plan

Render Craft entries as Inertia pages (Vue, React or Svelte) straight from Craft routing — no headless API, no second router.

- Package: `justinholtweb/craft-momentum` · handle `momentum` · namespace `JustinHoltWeb\Momentum`
- Editions: Lite / Pro · pricing: Lite free / Pro **TBD** (was $79 provisional; awaiting Justin)
- Laravel integration: inertiajs/inertia-laravel — Already required by Craft 6 core (the CP is an Inertia app), so no new dependency.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Why it exists
Craft 6 already runs its control panel on Inertia, and `inertiajs/inertia-laravel` ships with core. Nothing
lets a *site* do the same: today a Vue/React front end means going headless (GraphQL/Element API, a second
router, preview pain). Livewire Bridge covers Livewire; Momentum covers Inertia.

## Core idea
A section's template setting can name an Inertia component (`inertia:Pages/Article`). Craft's own element
routing still resolves the URI → entry; Momentum's front-end middleware renders that entry as an Inertia
response, with its field layout serialised into props.

## Status
- **2026-11-17 — phase 1 working.** Entry routing, root template, site middleware, prop serialisation
  (plain fields + relations; assets/Matrix paths written but not yet exercised live). 25 Pest tests,
  19 harness checks (`tests/Harness/check.sh`) incl. CP isolation, 409 version mismatch, XSS breakout.
- Learned: Craft 6 site templates live in `resources/views` when that dir exists (else `templates/`);
  `SetRoute` + `ControllerRoute` is the clean hook; Inertia v3 root is `<script data-page type=json>` + div.
- **Browser-verified** with Vue 3 + `@inertiajs/vue3` 3.11 (`tests/Harness/frontend/`): mounts with props,
  `<Link>` navigation is XHR (no reload), back button works. Live preview via a real draft + preview token
  renders the draft on first visit and over X-Inertia, and `page.url` keeps the token. 23 harness checks.
- Follow-up from the Wingman spike: serialise core Eloquent elements (non-`$hidden` model attributes) instead
  of the `{id,title,url}` fallback.
- Not yet verified: assets and Matrix props, multi-site, React/Svelte, SSR.
- Component names are the full path under the resolver root (`inertia:Pages/Article` → `./Pages/Article.vue`
  with a `./Pages/**` glob resolving `./${name}.vue`). Document this in the starter kits.

- **2026-11-18 — phases 2–4 done** (bf96057, 7e1bfea, pushed). Per-field-type transformers, level-by-level eager
  loading (9 Matrix blocks cost the same queries as 4; lazy is 3× more), Eloquent elements' non-`$hidden`
  attributes, field rules (exclude/rename/defer, `entryType.handle`), multisite props + `alternates`,
  `momentum.props()` for Twig + Blade, GraphQL `momentumPage`, Pro typed props / SSR / deferred fields.
  Security review fixes: nestedness by path (relations always gate + spend depth), site-scoped GraphQL gate,
  gated links dropped whole. **130 harness checks** (self-contained fixtures + cleanup, temporary `momentumfr`
  site, real Vue 3 + Inertia SSR server via `node` in the web container), 35 Pest tests, Larastan 6, Pint, port-check.
  Icon traced from the mockup (tile #439690). Docs in `docs/`, marketing page staged on justinholt.com.
- Verified headless (curl with X-Inertia headers): props for every core field type, CKEditor chunks with nested
  entries, deferred props + partial reloads, SSR head/body (Twig and Blade roots), multi-site. **Not verified:**
  React/Svelte adapters, a production SSR bundle built with `vite build --ssr`, Octane.

## Phases
1. ✅ **Front-end Inertia response (Lite).** Own middleware + root view for site requests only — must never touch
   the CP's `HandleInertiaRequests`. `inertia:` template prefix; `Momentum::render($component, $props)` for
   custom routes; shared props (site, currentUser, flashes, CSRF); Twig `{% inertia %}` root tag.
2. ✅ **Prop serialisation (Lite).** Field-layout-driven serializer with per-field-type transformers (plain,
   assets with transforms, relations to `propDepth`, Matrix/nested entries, CKEditor HTML). Eager loading
   planned from the layout so pages don't N+1. Opt-out/rename per field.
3. ✅ **Typed props (Pro).** `artisan craft:momentum:types` writes TypeScript interfaces per entry type.
4. ✅ **SSR + preview (Pro; preview stays in Lite — see questions).** Inertia SSR server support; live preview and share links render through the same
   component; partial reloads and deferred props for heavy relations.
5. ⏳ **Starters.** Not started (separate repos; see questions). Vite starter kits for Vue, React and Svelte (separate repos), docs site.

## Risks / open questions
- Confirm front-end requests can carry a different Inertia root view + version than the CP in the same app.
- Asset version hashing: front-end Vite manifest vs the CP's.
- Live preview iframes and Inertia's history state.

## Definition of done (every phase)
`bin/port-check craft-momentum` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.

## Questions for Justin

1. **Pro price?** PLAN had $79 (provisional); docs/site say TBD. Lite stays free.
2. **Edition split:** live preview and share links stayed in **Lite** (they already worked in phase 1 and
   pulling them would break Lite users' editing flow). Pro = typed props, SSR, deferred fields. OK?
3. **Starter kits (phase 5):** draft Vue/React/Svelte starters under `starters/` in this repo for you to split
   into repos, or create `craft-momentum-starter-{vue,react,svelte}` repos? (I didn't create any.)
4. **GraphQL shape:** `momentumPage` returns `props` as a JSON *string* (no JSON scalar in core). Keep it, add
   typed sub-fields, or drop the query and keep Momentum template-only?
5. **Users in props** are `{id, name}` only (name = Craft's friendly name, i.e. first name when set). Want
   `fullName`/photo too, opt-in?
6. **Site accent** for justinholt.com: I used teal **#4FB3AA** (the icon's #439690 lifted for the dark
   background). OK?

## Craft 6 beta notes (found while building)

- `Gql\Resolvers\Elements\User::prepareQuery(null, [])` throws "Undefined array key usergroups" when the schema
  has no user-group scope (Asset/Entry resolvers return an empty collection instead). Momentum checks
  `GqlHelper::canQueryUsers()` first.
- Adding site settings to an existing section didn't queue a resave in beta.1: existing entries never reached
  the new site until resaved. Relations are stored per source site and weren't copied on that resave.
- `@web` in a site's base URL isn't resolved in Craft 6 (it parses env vars, e.g. `$APP_URL/fr/`).
- CKEditor adopts (duplicates) any entry its HTML references on save; a foreign `<craft-entry>` can only come
  from content written around Craft. Momentum treats such entries as relations.
- Re-saving an entry type's field layout gives its elements new UIDs, which orphans stored content (content
  JSON is keyed by layout-element UID).
