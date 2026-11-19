#!/bin/bash
# Live checks against the Craft 6 harness (~/Sites/plugin-testing-craft6). Self-contained: creates its
# `momentum`-prefixed fixtures (fixtures.php, gql-fixtures.php), a temporary `momentumfr` site
# (multisite-fixtures.php, removed again at the end), and cleans everything up (cleanup.php).
# Shared fixtures it reads but doesn't own: the `articles` section with entries `hello-momentum-2` →
# `second-article-2` (+ a disabled `second-article`), `notes/a-twig-note`, and Wingman's `customers` field.
# Browser test app: copy tests/Harness/frontend/ into the harness's resources/js/ and `ddev npm run build`.
# Set KEEP=1 to leave the fixtures in place afterwards.
set -uo pipefail
exec < /dev/null   # ddev artisan tinker otherwise waits on stdin
H=/Users/jholt/Sites/plugin-testing-craft6
SRC=$(cd "$(dirname "$0")" && pwd)
U=https://plugin-testing-craft6.ddev.site
cd "$H" || exit 2
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
tk() { ddev artisan tinker --execute "$1" 2>/dev/null | grep -v -e WARNING -e DEPRECATED | tail -1; }
lock() { "$H/bin/harness-lock" "$@"; }
# Project-config writes can lose a race with another plugin's session (StaleResourceException): retry.
ptk() { local out i; for i in 1 2 3; do out=$(lock ddev artisan tinker --execute "require 'storage/momentum-$1.php';" 2>&1); echo "$out" | grep -q StaleResourceException || break; sleep 2; done; echo "$out"; }
DEFAULTS="'rootTemplate' => null, 'viteEntries' => null, 'propDepth' => 2, 'excludeFields' => null, 'renameFields' => null, 'assetTransforms' => null, 'shareCurrentUser' => true, 'assetVersion' => null, 'deferFields' => null, 'ssr' => false, 'ssrUrl' => null"
# Plugin settings live in project config, so a save can lose a race with another session: retry until saved or refused.
settings() { local out i; for i in 1 2 3 4; do out=$(tk "\$p=CraftCms\\Cms\\Support\\Facades\\Plugins::getPlugin('momentum'); echo CraftCms\\Cms\\Support\\Facades\\Plugins::savePluginSettings(\$p, array_merge([$DEFAULTS], ${1:-[]})) ? 'ok' : 'INVALID: '.implode(' ', \$p->getSettings()->errors()->all());"); case "$out" in ok) echo ok; return;; INVALID:*) echo "${out#INVALID: }"; return;; esac; sleep 1; done; echo "settings not saved: $out"; }
edition() { local i; for i in 1 2 3 4; do [ "$(tk "CraftCms\\Cms\\Support\\Facades\\Plugins::switchEdition('momentum', '$1'); echo CraftCms\\Cms\\Support\\Facades\\Plugins::getPlugin('momentum')->edition;")" = "$1" ] && return; sleep 1; done; echo "edition not switched to $1"; }
# pj <python expression over the parsed JSON `d`> (eval runs this script's own expressions, never page data); j(a, b, …) = "a/b/…"
pj() { python3 -I -c 'import json,sys
try: d=json.load(sys.stdin)
except Exception: print("not json"); sys.exit()
j=lambda *v: "/".join(str(x) for x in v)
try: print(eval(sys.argv[1]))
except Exception as e: print("ERR:" + type(e).__name__ + ":" + str(e))' "$1"; }
pagejson() { python3 -I -c 'import sys,re,json,html;s=sys.stdin.read();m=re.search(r"<script data-page=\"app\" type=\"application/json\">(.*?)</script>",s,re.S);print(html.unescape(m.group(1)) if m else "{}")'; }

# ---- Fixtures ----
mkdir -p "$H/resources/views/momentum-t"
cp "$SRC"/templates/props{.twig,-blade.blade.php} "$H/resources/views/momentum-t/"
cp "$SRC"/templates/momentum-root.blade.php "$H/resources/views/"
for f in fixtures gql-fixtures multisite-fixtures multisite-cleanup cleanup; do cp "$SRC/$f.php" "$H/storage/momentum-$f.php"; done
cp "$SRC/ssr-server.mjs" "$H/storage/momentum-ssr.mjs"
FIX=$(ptk fixtures | grep '^{"sink"')
[ -z "$FIX" ] && { echo "fixtures failed"; exit 1; }
id() { echo "$FIX" | pj "d['$1']"; }
SINK=$(id sink); RA=$(id a); RB=$(id b); ASSET=$(id asset)
echo "  (fixtures: kitchen sink $SINK, related $RA → $RB, asset $ASSET)"
edition lite
settings >/dev/null

# The asset version is the Vite manifest hash when a build exists; X-Inertia requests must echo it.
V=$(curl -sk "$U/articles/hello-momentum-2" | pagejson | pj 'd["version"]')
xi() { curl -sk -H 'X-Inertia: true' -H "X-Inertia-Version: $V" "$@"; }
K="$U/momentum-kitchen/momentum-kitchen-sink"

# ---- Phase 1: routing, root template, shared props ----
page=$(xi "$U/articles/hello-momentum-2")
ok "X-Inertia visit returns the section's component" "$(echo "$page" | pj 'd["component"]')" "Pages/Article"
ok "entry title in props" "$(echo "$page" | pj 'd["props"]["entry"]["title"]')" "Hello Momentum"
ok "plain text field in props" "$(echo "$page" | pj 'd["props"]["entry"]["fields"]["summary"]')" "Inertia pages from Craft routing."
ok "relation serialised one hop" "$(echo "$page" | pj 'd["props"]["entry"]["fields"]["related"][0]["title"]')" "Second Article"
ok "shared site prop" "$(echo "$page" | pj 'd["props"]["site"]["handle"]')" "default"
ok "guest currentUser is null" "$(echo "$page" | pj 'd["props"]["currentUser"]')" "None"
ok "single-site install: no alternates" "$(echo "$page" | pj 'd["props"]["alternates"]')" "[]"

full=$(curl -sk "$U/articles/hello-momentum-2")
ok "first visit renders root template with page JSON" "$(echo "$full" | grep -c '<script data-page="app" type="application/json">')" "1"
ok "first visit has mount element" "$(echo "$full" | grep -c '<div id="app"></div>')" "1"
tk '$e=CraftCms\Cms\Entry\Elements\Entry::find()->slug("second-article-2")->one(); $e->setFieldValue("summary", "</script><script>alert(1)</script>"); CraftCms\Cms\Support\Facades\Elements::saveElement($e); echo 1;' >/dev/null
xss=$(curl -sk "$U/articles/hello-momentum-2")
ok "field HTML can't break out of the page JSON" "$(echo "$xss" | grep -c '<script>alert(1)')" "0"
ok "field HTML is still in the page JSON (escaped)" "$(echo "$xss" | grep -c 'u003Cscript\\u003Ealert(1)')" "1"
tk '$e=CraftCms\Cms\Entry\Elements\Entry::find()->slug("second-article-2")->one(); $e->setFieldValue("summary", "The related one."); CraftCms\Cms\Support\Facades\Elements::saveElement($e); echo 1;' >/dev/null

settings "['excludeFields' => 'internalNotes', 'propDepth' => 0, 'assetVersion' => 'v2']" >/dev/null
page=$(curl -sk -H 'X-Inertia: true' -H 'X-Inertia-Version: v2' "$U/articles/hello-momentum-2")
ok "excluded field is not sent" "$(echo "$page" | pj '"internalNotes" in d["props"]["entry"]["fields"]')" "False"
ok "depth 0 sends related ids" "$(echo "$page" | pj 'd["props"]["entry"]["fields"]["related"]')" "[10]"
ok "stale asset version gets 409" "$(curl -sk -o /dev/null -w '%{http_code}' -H 'X-Inertia: true' -H 'X-Inertia-Version: v1' "$U/articles/hello-momentum-2")" "409"
settings >/dev/null

T=$(ddev artisan tinker --execute "$(cat "$SRC/preview-token.php")" 2>&1 | grep -o 'TOKEN=[^ ]*' | cut -d= -f2)
ok "live preview renders the draft (first visit)" "$(curl -sk "$U/articles/hello-momentum-2?token=$T" | grep -c 'DRAFT-ONLY summary')" "1"
ok "live preview renders the draft (X-Inertia)" "$(xi "$U/articles/hello-momentum-2?token=$T" | pj 'd["props"]["entry"]["fields"]["summary"]')" "DRAFT-ONLY summary"
ok "preview page URL keeps the token" "$(xi "$U/articles/hello-momentum-2?token=$T" | pj '"token=" in d["url"]')" "True"
ok "live page doesn't show the draft" "$(xi "$U/articles/hello-momentum-2" | pj 'd["props"]["entry"]["fields"]["summary"]')" "Inertia pages from Craft routing."
tk '$e=CraftCms\Cms\Entry\Elements\Entry::find()->slug("hello-momentum-2")->one(); foreach (CraftCms\Cms\Entry\Elements\Entry::find()->draftOf($e)->status(null)->all() as $d) CraftCms\Cms\Support\Facades\Elements::deleteElement($d, true); echo 1;' >/dev/null

ok "Twig sections are untouched" "$(curl -sk "$U/notes/a-twig-note" | grep -c 'id="twig-note"')" "1"
ok "disabled entry 404s" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/articles/second-article")" "404"
ok "unknown URI 404s" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/articles/does-not-exist")" "404"

# A fresh, verified CP session (other sessions on the shared harness sometimes end ours).
cpj() { local j i; for i in 1 2 3; do j=$("$H/bin/cp-session" 2>/dev/null) && [ "$(curl -sk -b "$j" -o /dev/null -w '%{http_code}' "$U/admin/dashboard")" = 200 ] && { echo "$j"; return; }; sleep 2; done; echo /dev/null; }
J=$(cpj)
CV=$(curl -sk -b "$J" "$U/admin/dashboard" | pagejson | pj 'd["version"]')
cp=$(curl -sk -b "$J" -H 'X-Inertia: true' -H "X-Inertia-Version: $CV" -H 'X-Requested-With: XMLHttpRequest' "$U/admin/dashboard")
ok "CP keeps Craft's own Inertia page" "$(echo "$cp" | pj 'd["component"]')" "Dashboard"
ok "CP gets no Momentum shared props" "$(echo "$cp" | pj '"site" in d["props"] and "currentUser" in d["props"]')" "False"
ok "settings page loads" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' "$U/admin/settings/plugins/momentum")" "200"

# ---- Phase 2: field-type props (kitchen sink: every transformer) ----
settings "['assetTransforms' => 'momentumThumb']" >/dev/null
page=$(xi "$K")
f() { echo "$page" | pj "d['props']['entry']['fields']$1"; }
ok "kitchen: component from the section's template" "$(echo "$page" | pj 'd["component"]')" "Pages/Kitchen"
ok "plain text" "$(f "['momentumHeading']")" "Everything at once"
ok "asset: alt, dimensions, kind" "$(echo "$page" | pj 'j(*[d["props"]["entry"]["fields"]["momentumImage"][0][k] for k in ("alt","width","height","kind","filename")])')" "An orange rectangle/120/80/image/momentum-pixel.png"
ok "asset: url on the volume" "$(f "['momentumImage'][0]['url']")" "$U/storage/momentum/momentum-pixel.png"
ok "asset: named transform URL" "$(f "['momentumImage'][0]['transforms']['momentumThumb']" | grep -c 'momentumThumb\|_64x64\|transform')" "1"
ok "matrix: blocks in order with their entry types" "$(echo "$page" | pj '",".join(b["type"] for b in d["props"]["entry"]["fields"]["momentumBlocks"])')" "momentumText,momentumGallery,momentumGallery,momentumGallery"
ok "matrix: block fields" "$(f "['momentumBlocks'][0]['fields']['momentumHeading']")" "First block"
ok "matrix: nested blocks don't spend depth (block → A → B)" "$(f "['momentumBlocks'][1]['fields']['momentumRelated'][0]['fields']['momentumRelated'][0]['title']")" "Related B"
ok "matrix: asset inside a block" "$(f "['momentumBlocks'][1]['fields']['momentumImage'][0]['id']")" "$ASSET"
ok "ckeditor: html with ref tags parsed, nested entry left out" "$(f "['momentumBody']['html']")" "<p>Intro <strong>bold</strong></p><p>Outro</p>"
ok "ckeditor: chunks in order (markup, entry, markup)" "$(echo "$page" | pj '",".join(c["type"] for c in d["props"]["entry"]["fields"]["momentumBody"]["chunks"])')" "markup,entry,markup"
ok "ckeditor: nested entry serialised" "$(f "['momentumBody']['chunks'][1]['entry']['fields']['momentumHeading']")" "Nested in CKEditor"
ok "link: url + label" "$(echo "$page" | pj 'j(d["props"]["entry"]["fields"]["momentumLink"]["url"], d["props"]["entry"]["fields"]["momentumLink"]["label"], d["props"]["entry"]["fields"]["momentumLink"]["type"])')" "https://example.com/momentum/Example/url"
ok "dropdown: {value, label}" "$(f "['momentumTone']")" "{'value': 'cool', 'label': 'Cool'}"
ok "checkboxes: selected options only" "$(echo "$page" | pj '",".join(o["value"] for o in d["props"]["entry"]["fields"]["momentumTags"])')" "red,blue"
ok "lightswitch" "$(f "['momentumFeatured']")" "True"
ok "money: decimal string + currency" "$(echo "$page" | pj 'j(d["props"]["entry"]["fields"]["momentumPrice"]["amount"], d["props"]["entry"]["fields"]["momentumPrice"]["currency"])')" "12.50/USD"
ok "users: only id and name keys" "$(echo "$page" | pj 'sorted(d["props"]["entry"]["fields"]["momentumAuthor"][0].keys())')" "['id', 'name']"
ok "users: no email anywhere in the page" "$(echo "$page" | grep -c 'momentum.test')" "0"
ok "addresses field absent from fields" "$(echo "$page" | pj '"momentumAddress" in d["props"]["entry"]["fields"]')" "False"
ok "relations: two hops deep at depth 2" "$(f "['momentumRelated'][0]['fields']['momentumRelated'][0]['title']")" "Related B"
ok "eloquent element: model attributes, never \$hidden" "$(xi "$U/articles/hello-momentum-2" | pj 'j(sorted(d["props"]["entry"]["fields"]["customers"][0]["attributes"].keys()), "email" in json.dumps(d))')" "['company', 'name']/False"

settings "['propDepth' => 1]" >/dev/null
ok "depth 1: related entry serialised, its relations as ids" "$(xi "$K" | pj 'd["props"]["entry"]["fields"]["momentumRelated"][0]["fields"]["momentumRelated"]')" "[$RB]"
settings "['excludeFields' => 'momentumKitchen.momentumSecret']" >/dev/null
page=$(xi "$K")
ok "qualified exclude: gone on the kitchen entry type" "$(echo "$page" | pj '"momentumSecret" in d["props"]["entry"]["fields"]')" "False"
ok "qualified exclude: kept on other entry types (Matrix block)" "$(f "['momentumBlocks'][0]['fields']['momentumSecret']")" "BLOCK-SECRET"
settings "['excludeFields' => \"momentumSecret\\ninternalNotes\"]" >/dev/null
ok "bare exclude: gone everywhere" "$(xi "$K" | grep -c 'SECRET')" "0"
settings "['renameFields' => \"momentumHeading: heading\\nmomentumGallery.momentumImage: photos\"]" >/dev/null
page=$(xi "$K")
ok "rename: prop sent under the new name" "$(f "['heading']")/$(echo "$page" | pj '"momentumHeading" in d["props"]["entry"]["fields"]')" "Everything at once/False"
ok "qualified rename: only on that entry type" "$(f "['momentumBlocks'][1]['fields']['photos'][0]['id']")/$(echo "$page" | pj '"momentumImage" in d["props"]["entry"]["fields"]')" "$ASSET/True"
ok "settings: a bad rename line is refused" "$(settings "['renameFields' => 'momentumHeading -> 9bad']")" "Write each rename as \`handle: propName\` (prop names are letters, numbers and underscores)."
ok "settings: a bad exclude handle is refused" "$(settings "['excludeFields' => 'bad handle!']" | grep -c 'isn’t a field handle')" "1"
settings >/dev/null

# Eager loading: the many-blocks entry (9 gallery blocks) costs no more queries than the 4-block sink.
qc() { tk "\$e=CraftCms\\Cms\\Entry\\Elements\\Entry::find()->section('momentumKitchen')->slug('$1')->one(); Illuminate\\Support\\Facades\\DB::enableQueryLog(); $2; echo count(Illuminate\\Support\\Facades\\DB::getQueryLog());"; }
EAGER='app(JustinHoltWeb\Momentum\Momentum::class)->serializer()->root($e)'
LAZY='new JustinHoltWeb\Momentum\Props\PropSerializer(transformers: app(JustinHoltWeb\Momentum\Props\Transformers\Transformers::class), eagerLoad: false)->root($e)'
QS=$(qc momentum-kitchen-sink "$EAGER"); QM=$(qc momentum-many-blocks "$EAGER"); QL=$(qc momentum-many-blocks "$LAZY")
echo "  (queries: sink $QS, 9 blocks $QM eager / $QL lazy)"
ok "eager loading: 9 blocks cost no more queries than 4" "$([ "$QM" -le "$QS" ] && echo yes || echo "no ($QM > $QS)")" "yes"
ok "eager loading: under half the queries of lazy loading" "$([ $((QM * 2)) -lt "$QL" ] && echo yes || echo "no ($QM vs $QL)")" "yes"

# ---- Twig and Blade: `momentum.props(entry)` parity ----
tw=$(curl -sk "$U/momentum-t/props?slug=momentum-kitchen-sink" | python3 -I -c 'import sys,re;print(re.search(r"<script id=\"props\" type=\"application/json\">(.*?)</script>",sys.stdin.read(),re.S).group(1))')
bl=$(curl -sk "$U/momentum-t/props-blade?slug=momentum-kitchen-sink" | python3 -I -c 'import sys,re;print(re.search(r"<script id=\"props\" type=\"application/json\">(.*?)</script>",sys.stdin.read(),re.S).group(1))')
ok "twig: momentum.props(entry) gives the page props" "$(echo "$tw" | pj 'd["entry"]["fields"]["momentumHeading"]')" "Everything at once"
ok "blade: \$momentum->props(\$entry) gives the page props" "$(echo "$bl" | pj 'd["entry"]["fields"]["momentumHeading"]')" "Everything at once"
ok "twig and blade props are identical" "$(python3 -I -c 'import json,sys;print(json.loads(sys.argv[1])==json.loads(sys.argv[2]))' "$tw" "$bl")" "True"
ok "twig and blade props match the Inertia page's entry" "$(python3 -I -c 'import json,sys;print(json.loads(sys.argv[1])["entry"]==json.loads(sys.argv[2])["props"]["entry"])' "$tw" "$(xi "$K")")" "True"
ok "componentFor(entry) in both engines" "$(curl -sk "$U/momentum-t/props?slug=momentum-kitchen-sink" | grep -o '<p id="component">[^<]*' | sed 's/.*>//')/$(curl -sk "$U/momentum-t/props-blade?slug=momentum-kitchen-sink" | grep -o '<p id="component">[^<]*' | sed 's/.*>//')" "Pages/Kitchen/Pages/Kitchen"

# Root templates: Twig (default) and Blade output the same page object.
twr=$(curl -sk "$K" | pagejson)
settings "['rootTemplate' => 'momentum-root']" >/dev/null
blade=$(curl -sk "$K")
ok "Blade root template is used" "$(echo "$blade" | grep -c momentum-blade-root)" "1"
ok "Twig and Blade roots output the same page JSON" "$(python3 -I -c 'import json,sys;print(json.loads(sys.argv[1])==json.loads(sys.argv[2]))' "$twr" "$(echo "$blade" | pagejson)")" "True"
settings >/dev/null

# ---- GraphQL: momentumPage ----
ptk gql-fixtures >/dev/null
# gq <schema> <query> <python expression over the response `r` (d = data, p = parsed props)>
gq() { curl -sk -H "Authorization: Bearer $(printf 'momentum%s' "$1" | awk '{ s=$0; while (length(s) < 32) s=s "0"; print s }')" -H 'Content-Type: application/json' \
  --data "$(python3 -I -c 'import json,sys; print(json.dumps({"query": sys.argv[1]}))' "$2")" "$U/actions/graphql/api" \
  | python3 -I -c 'import json,sys
try: r=json.load(sys.stdin)
except Exception: print("not json"); sys.exit()
if r.get("errors"): print("ERR:" + "; ".join(e.get("message","") for e in r["errors"])); sys.exit()
d=r.get("data") or {}; m=d.get("momentumPage"); p=json.loads(m["props"]) if m and "props" in m else None; j=lambda *v: "/".join(str(x) for x in v)
print(eval(sys.argv[1]))' "$3"; }
KQ='{ momentumPage(uri: "momentum-kitchen/momentum-kitchen-sink") { component url site props } }'
ok "gql: component, site and props for an Inertia entry" "$(gq full "$KQ" 'j(m["component"], m["site"], p["entry"]["title"])')" "Pages/Kitchen/default/Kitchen Sink"
ok "gql: props match the Inertia page's entry" "$(gq full "$KQ" 'json.dumps(p["entry"], sort_keys=True)' | python3 -I -c 'import json,sys;print(json.loads(sys.stdin.read())==json.loads(sys.argv[1])["props"]["entry"])' "$(xi "$K")")" "True"
# (bash 3.2 mangles nested quotes inside "$(…)", so queries with variables are built first)
QID="{ momentumPage(id: $SINK) { component } }"
QBOTH="{ momentumPage(id: $SINK, uri: \"x\") { component } }"
ok "gql: by id" "$(gq full "$QID" 'm["component"]')" "Pages/Kitchen"
ok "gql: unknown uri → null" "$(gq full '{ momentumPage(uri: "momentum-kitchen/nope") { component } }' 'm')" "None"
ok "gql: id and uri together refused" "$(gq full "$QBOTH" 'm')" "ERR:Pass either \`id\` or \`uri\`."
ok "gql: schema without the component has no momentumPage" "$(gq nopages "$KQ" 'm')" "ERR:Cannot query field \"momentumPage\" on type \"Query\"."
ok "gql: entry in a section the schema can't read → null" "$(gq noarticles '{ momentumPage(uri: "articles/hello-momentum-2") { component } }' 'm')" "None"
ok "gql: related entry the schema can read is kept" "$(gq noarticles "$KQ" 'p["entry"]["fields"]["momentumRelated"][0]["title"]')" "Related A"
ok "gql: assets from a volume the schema can't read are left out" "$(gq noarticles "$KQ" 'j(p["entry"]["fields"]["momentumImage"], p["entry"]["fields"]["momentumBlocks"][1]["fields"]["momentumImage"])')" "[]/[]"
ok "gql: users the schema can't read are left out" "$(gq noarticles "$KQ" 'p["entry"]["fields"]["momentumAuthor"]')" "[]"
ok "gql: full schema keeps assets and users" "$(gq full "$KQ" 'j(p["entry"]["fields"]["momentumImage"][0]["id"], p["entry"]["fields"]["momentumAuthor"][0]["name"])')" "$ASSET/Ada"
ok "gql: eloquent/other element types are left out" "$(gq full '{ momentumPage(uri: "articles/hello-momentum-2") { props } }' 'p["entry"]["fields"]["customers"]')" "[]"
ok "gql: no email, no excluded field" "$(settings "['excludeFields' => 'momentumSecret']" >/dev/null; gq full "$KQ" '("momentum.test" in m["props"], "SECRET" in m["props"])')" "(False, False)"
settings >/dev/null
ok "gql: unknown site refused like an unreadable one" "$(gq full '{ momentumPage(uri: "x", site: "nope") { component } }' 'm')" "ERR:This schema can’t read the “nope” site."
J=$(cpj)
ok "gql: CP schema editor offers the permission" "$(curl -sk -b "$J" "$U/admin/graphql/schemas/new" | grep -c 'momentum.pages:read' | awk '{print ($1>0)?"yes":"no"}')" "yes"

# ---- Multisite: a temporary `momentumfr` site (removed at the end) ----
ptk multisite-fixtures >/dev/null
if [ "$(tk "echo CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('momentumfr') ? 'yes' : 'no';")" = yes ]; then
  FR="$U/momentumfr/cuisine/momentum-kitchen-sink"
  page=$(xi "$FR")
  ok "multisite: French URL renders the French entry" "$(echo "$page" | pj 'j(d["component"], d["props"]["entry"]["title"])')" "Pages/Kitchen/Évier de cuisine"
  ok "multisite: shared site prop is the current site" "$(echo "$page" | pj 'j(d["props"]["site"]["handle"], d["props"]["site"]["language"])')" "momentumfr/fr-FR"
  ok "multisite: relations resolve in the current site" "$(echo "$page" | pj 'j(d["props"]["entry"]["fields"]["momentumRelated"][0]["title"], d["props"]["entry"]["fields"]["momentumRelated"][0]["url"])')" "Lié A/$U/momentumfr/cuisine/momentum-related-a"
  ok "multisite: alternates link every site's URL" "$(echo "$page" | pj '",".join(a["site"] + "=" + a["url"].replace("'"$U"'", "") + ("*" if a["current"] else "") for a in d["props"]["alternates"])')" "default=/momentum-kitchen/momentum-kitchen-sink,momentumfr=/momentumfr/cuisine/momentum-kitchen-sink*"
  ok "multisite: English page is unchanged" "$(xi "$K" | pj 'j(d["props"]["entry"]["title"], d["props"]["site"]["handle"])')" "Kitchen Sink/default"
  ok "multisite: twig props on the French site" "$(curl -sk "$U/momentumfr/momentum-t/props?slug=momentum-kitchen-sink" | python3 -I -c 'import sys,re,json;print(json.loads(re.search(r"<script id=\"props\" type=\"application/json\">(.*?)</script>",sys.stdin.read(),re.S).group(1))["entry"]["title"])')" "Évier de cuisine"
  ok "multisite: blade props on the French site" "$(curl -sk "$U/momentumfr/momentum-t/props-blade?slug=momentum-kitchen-sink" | python3 -I -c 'import sys,re,json;print(json.loads(re.search(r"<script id=\"props\" type=\"application/json\">(.*?)</script>",sys.stdin.read(),re.S).group(1))["entry"]["title"])')" "Évier de cuisine"
  ok "multisite gql: a site the schema can't read is refused" "$(gq full '{ momentumPage(uri: "cuisine/momentum-kitchen-sink", site: "momentumfr") { component } }' 'm')" "ERR:This schema can’t read the “momentumfr” site."
  tk "\$s=collect(CraftCms\\Cms\\Support\\Facades\\Gql::getSchemas())->first(fn(\$s)=>\$s->name==='momentum_full'); \$s->scope=[...\$s->scope, 'sites.'.CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('momentumfr')->uid.':read']; CraftCms\\Cms\\Support\\Facades\\Gql::saveSchema(\$s); CraftCms\\Cms\\Support\\Facades\\Gql::invalidateCaches(); echo 1;" >/dev/null
  ok "multisite gql: the French page by URI" "$(gq full '{ momentumPage(uri: "cuisine/momentum-kitchen-sink", site: "momentumfr") { site props } }' 'j(m["site"], p["entry"]["title"], p["entry"]["fields"]["momentumRelated"][0]["title"])')" "momentumfr/Évier de cuisine/Lié A"
  ok "multisite gql: alternates only list sites the schema can read" "$(gq noarticles "$KQ" 'len(p["alternates"])')" "1"
  ftoggle() { tk "\$e=CraftCms\\Cms\\Entry\\Elements\\Entry::find()->id($SINK)->site('momentumfr')->status(null)->one(); \$e->enabledForSite=$1; echo CraftCms\\Cms\\Support\\Facades\\Elements::saveElement(\$e) ? 'saved' : 'failed';"; }
  ok "multisite: disabled on one site → no alternates left, that URL 404s" "$(ftoggle false >/dev/null; xi "$K" | pj 'len(d["props"]["alternates"])')/$(curl -sk -o /dev/null -w '%{http_code}' "$FR")" "0/404"
  ftoggle true >/dev/null
else
  echo "- multisite skipped (couldn't create the momentumfr site)"
fi
ptk multisite-cleanup | grep -q removed && echo "  (momentumfr site removed)"

# ---- Pro ----
ok "lite: types command refuses" "$(ddev artisan craft:momentum:types --stdout 2>&1 | grep -c 'Pro feature')" "1"
settings "['deferFields' => 'momentumRelated', 'ssr' => true, 'ssrUrl' => 'http://127.0.0.1:13716']" >/dev/null
ok "lite: deferred fields setting is ignored" "$(xi "$K" | pj 'j("deferredProps" in d, d["props"]["entry"]["fields"]["momentumRelated"][0]["title"])')" "False/Related A"
settings >/dev/null
edition pro

# Typed props
ddev artisan craft:momentum:types --output=storage/momentum-types.d.ts >/dev/null 2>&1
TS="$H/storage/momentum-types.d.ts"
ok "types: file written" "$(test -s "$TS" && echo yes)" "yes"
ok "types: an entry type per Inertia section's entry types" "$(grep -c '^export type MomentumKitchenEntry = MomentumEntry<"momentumKitchen", {' "$TS")" "1"
ok "types: page map entry for the component" "$(grep -c '"Pages/Kitchen": MomentumPageProps<MomentumKitchenEntry>;' "$TS")/$(grep -c '"Pages/Article": MomentumPageProps<ArticleEntry>;' "$TS")" "1/1"
ok "types: dropdown options as a literal union" "$(grep -o 'momentumTone: { value: "warm" | "cool"; label: string } | null' "$TS" | head -1)" 'momentumTone: { value: "warm" | "cool"; label: string } | null'
ok "types: matrix as a union of its entry types" "$(grep -o 'momentumBlocks: Array<MomentumTextEntry | MomentumGalleryEntry>' "$TS" | head -1)" "momentumBlocks: Array<MomentumTextEntry | MomentumGalleryEntry>"
ok "types: assets, users, rich text, money" "$(grep -o 'momentumImage: Array<MomentumAsset | number>\|momentumAuthor: Array<MomentumUser | number>\|momentumBody: MomentumRichText | null\|momentumPrice: { amount' "$TS" | sort -u | wc -l | tr -d ' ')" "4"
ok "types: addresses never typed" "$(grep -c 'momentumAddress' "$TS")" "0"
settings "['excludeFields' => 'momentumSecret', 'renameFields' => 'momentumHeading: heading', 'deferFields' => 'momentumRelated']" >/dev/null
ddev artisan craft:momentum:types --output=storage/momentum-types.d.ts >/dev/null 2>&1
ok "types: excluded fields left out, renames applied, deferred optional" "$(grep -c 'momentumSecret' "$TS")/$(grep -c 'heading: string | null' "$TS" | awk '{print ($1>0)?"yes":"no"}')/$(grep -c 'momentumRelated?: ' "$TS" | awk '{print ($1>0)?"yes":"no"}')" "0/yes/yes"
ok "types: refuses an output path outside the project" "$(ddev artisan craft:momentum:types --output=../evil.ts 2>&1 | grep -c 'relative path')/$(ddev artisan craft:momentum:types --output=/tmp/x.ts 2>&1 | grep -c 'relative path')" "1/1"
ok "types: unknown section refused" "$(ddev artisan craft:momentum:types --section=nope --stdout 2>&1 | grep -c 'Unknown section')" "1"
if [ -x "$H/node_modules/.bin/tsc" ]; then
  ok "types: tsc accepts the declarations" "$(ddev exec "node_modules/.bin/tsc --noEmit --strict storage/momentum-types.d.ts" >/dev/null 2>&1; echo $?)" "0"
else
  # No TypeScript compiler in the harness: Node's type stripping still parses every declaration.
  cp "$TS" "$H/storage/momentum-types-check.ts"
  ok "types: the declarations parse as TypeScript (node --experimental-strip-types)" "$(ddev exec "node --experimental-strip-types --no-warnings storage/momentum-types-check.ts" >/dev/null 2>&1; echo $?)" "0"
  rm -f "$H/storage/momentum-types-check.ts"
fi

# Deferred props + partial reloads
settings "['deferFields' => 'momentumRelated']" >/dev/null
page=$(xi "$K")
ok "deferred: field left out of the first response" "$(echo "$page" | pj '"momentumRelated" in d["props"]["entry"]["fields"]')" "False"
ok "deferred: listed for the client to fetch" "$(echo "$page" | pj 'd["deferredProps"]')" "{'momentum': ['entry.fields.momentumRelated']}"
part=$(xi -H 'X-Inertia-Partial-Component: Pages/Kitchen' -H 'X-Inertia-Partial-Data: entry.fields.momentumRelated' "$K")
ok "deferred: partial reload returns the field" "$(echo "$part" | pj 'd["props"]["entry"]["fields"]["momentumRelated"][0]["title"]')" "Related A"
ok "deferred: partial reload sends only that field" "$(echo "$part" | pj 'j(sorted(d["props"].keys()), sorted(d["props"]["entry"]["fields"].keys()))')" "['entry', 'errors']/['momentumRelated']"
ok "partial reload of a shared prop" "$(xi -H 'X-Inertia-Partial-Component: Pages/Kitchen' -H 'X-Inertia-Partial-Data: site' "$K" | pj 'sorted(d["props"].keys())')" "['errors', 'site']"
ok "deferred fields never deferred in GraphQL" "$(gq full "$KQ" 'p["entry"]["fields"]["momentumRelated"][0]["title"]')" "Related A"
ok "deferred fields never deferred in momentum.props()" "$(curl -sk "$U/momentum-t/props?slug=momentum-kitchen-sink" | python3 -I -c 'import sys,re,json;print(json.loads(re.search(r"<script id=\"props\" type=\"application/json\">(.*?)</script>",sys.stdin.read(),re.S).group(1))["entry"]["fields"]["momentumRelated"][0]["title"])')" "Related A"
settings >/dev/null

# SSR (a real Inertia SSR server: Vue 3 + @inertiajs/vue3/server, run with node in the web container)
# (the [r] keeps pkill from matching its own shell)
ssr_stop() { ddev exec "pkill -f 'node storage/momentum-ss[r]'" >/dev/null 2>&1; }
ssr_stop
ddev exec 'PORT=13716 setsid nohup node storage/momentum-ssr.mjs > storage/logs/momentum-ssr.log 2>&1 < /dev/null &' >/dev/null 2>&1
for i in 1 2 3 4 5 6 7 8 9 10; do ddev exec "curl -s http://127.0.0.1:13716/health" 2>/dev/null | grep -q OK && break; sleep 1; done
settings "['ssr' => true, 'ssrUrl' => 'http://127.0.0.1:13716']" >/dev/null
ssr=$(curl -sk "$K")
ok "ssr: body rendered on the server" "$(echo "$ssr" | grep -c '<h1 id="momentum-title">Kitchen Sink</h1>')" "1"
ok "ssr: head from the server (title)" "$(echo "$ssr" | grep -c 'SSR Kitchen Sink')" "1"
ok "ssr: page JSON still embedded for hydration" "$(echo "$ssr" | pagejson | pj 'd["component"]')" "Pages/Kitchen"
settings "['ssr' => true, 'ssrUrl' => 'http://127.0.0.1:13716', 'rootTemplate' => 'momentum-root']" >/dev/null
ok "ssr: Blade root gets the same server render" "$(curl -sk "$K" | grep -c '<h1 id="momentum-title">Kitchen Sink</h1>')/$(curl -sk "$K" | grep -c momentum-blade-root)" "1/1"
settings "['ssr' => true, 'ssrUrl' => 'http://127.0.0.1:13716']" >/dev/null
ok "ssr: X-Inertia visits are plain JSON" "$(xi "$K" | pj 'd["component"]')" "Pages/Kitchen"
J=$(cpj)
ok "ssr: the control panel is never server-rendered by Momentum" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' "$U/admin/dashboard")/$(curl -sk -b "$J" "$U/admin/dashboard" | grep -c 'momentum-title')" "200/0"
edition lite
ok "ssr: Lite ignores the SSR setting" "$(curl -sk "$K" | grep -c 'momentum-title')" "0"
edition pro
ssr_stop
ok "ssr: server down → client-side render, still 200" "$(curl -sk -o /dev/null -w '%{http_code}' "$K")/$(curl -sk "$K" | grep -c '<div id="app"></div>')" "200/1"
settings "['ssr' => true, 'ssrUrl' => 'http://127.0.0.1:1']" >/dev/null
ok "ssr: unreachable URL → client-side render" "$(curl -sk "$K" | grep -c '<div id="app"></div>')" "1"
ok "settings: SSR URL must be http(s)" "$(settings "['ssrUrl' => 'file:///etc/passwd']" | grep -c 'ssr url')" "1"

# ---- Cleanup ----
settings >/dev/null
edition lite
rm -f "$TS" "$H/resources/views/momentum-t/props.twig" "$H/resources/views/momentum-t/props-blade.blade.php"
rmdir "$H/resources/views/momentum-t" 2>/dev/null
if [ -z "${KEEP:-}" ]; then
  ptk cleanup >/dev/null
  ok "cleanup: fixtures removed" "$(tk "echo json_encode([CraftCms\\Cms\\Support\\Facades\\Sections::getSectionByHandle('momentumKitchen') === null, CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('momentumfr') === null]);")" "[true,true]"
fi
rm -f "$H"/storage/momentum-*.php "$H/storage/momentum-ssr.mjs"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
