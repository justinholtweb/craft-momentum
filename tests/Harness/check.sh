#!/bin/bash
# Live checks against the Craft 6 harness (~/Sites/plugin-testing-craft6).
# Prereqs (one-off): sections `articles` (template `inertia:Pages/Article`, uri `articles/{slug}`) and
# `notes` (template `notes/_entry`), entries `hello-momentum-2` → related `second-article-2`, a disabled
# `second-article`, then `tests/Harness/seed-fields.php` run through `ddev artisan tinker --execute`.
# Browser test app: copy tests/Harness/frontend/ into the harness's resources/js/ and `ddev npm run build`.
set -uo pipefail
exec < /dev/null   # ddev artisan tinker otherwise waits on stdin
H=/Users/jholt/Sites/plugin-testing-craft6
U=https://plugin-testing-craft6.ddev.site
cd "$H" || exit 2
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
settings() { ddev artisan tinker --execute "\$p=\CraftCms\Cms\Support\Facades\Plugins::getPlugin('momentum'); \CraftCms\Cms\Support\Facades\Plugins::savePluginSettings(\$p, $1);" >/dev/null 2>&1; }
json() { python3 -I -c "import json,sys;d=json.load(sys.stdin);print($1)"; }

settings "['excludeFields' => null, 'propDepth' => 2, 'assetVersion' => null, 'viteEntries' => null, 'rootTemplate' => null]"
# The asset version is the Vite manifest hash when a build exists; X-Inertia requests must echo it.
V=$(curl -sk "$U/articles/hello-momentum-2" | python3 -I -c 'import sys,re,json,html;s=sys.stdin.read();print(json.loads(html.unescape(re.search(r"type=\"application/json\">(.*?)</script>",s,re.S).group(1)))["version"])')
xi() { curl -sk -H 'X-Inertia: true' -H "X-Inertia-Version: $V" "$@"; }

page=$(xi "$U/articles/hello-momentum-2")
ok "X-Inertia visit returns the section's component" "$(echo "$page" | json 'd["component"]')" "Pages/Article"
ok "entry title in props" "$(echo "$page" | json 'd["props"]["entry"]["title"]')" "Hello Momentum"
ok "plain text field in props" "$(echo "$page" | json 'd["props"]["entry"]["fields"]["summary"]')" "Inertia pages from Craft routing."
ok "relation serialised one hop" "$(echo "$page" | json 'd["props"]["entry"]["fields"]["related"][0]["title"]')" "Second Article"
ok "shared site prop" "$(echo "$page" | json 'd["props"]["site"]["handle"]')" "default"
ok "guest currentUser is null" "$(echo "$page" | json 'd["props"]["currentUser"]')" "None"

full=$(curl -sk "$U/articles/hello-momentum-2")
ok "first visit renders root template with page JSON" "$(echo "$full" | grep -c '<script data-page="app" type="application/json">')" "1"
ok "first visit has mount element" "$(echo "$full" | grep -c '<div id="app"></div>')" "1"
ddev artisan tinker --execute '$e=\CraftCms\Cms\Entry\Elements\Entry::find()->slug("second-article-2")->one(); $e->setFieldValue("summary", "</script><script>alert(1)</script>"); \CraftCms\Cms\Support\Facades\Elements::saveElement($e);' >/dev/null 2>&1
xss=$(curl -sk "$U/articles/hello-momentum-2")
ok "field HTML can't break out of the page JSON" "$(echo "$xss" | grep -c '<script>alert(1)')" "0"
ok "field HTML is still in the page JSON (escaped)" "$(echo "$xss" | grep -c 'u003Cscript\\u003Ealert(1)')" "1"
ddev artisan tinker --execute '$e=\CraftCms\Cms\Entry\Elements\Entry::find()->slug("second-article-2")->one(); $e->setFieldValue("summary", "The related one."); \CraftCms\Cms\Support\Facades\Elements::saveElement($e);' >/dev/null 2>&1

settings "['excludeFields' => 'internalNotes', 'propDepth' => 0, 'assetVersion' => 'v2']"
page=$(curl -sk -H 'X-Inertia: true' -H 'X-Inertia-Version: v2' "$U/articles/hello-momentum-2")
ok "excluded field is not sent" "$(echo "$page" | json '"internalNotes" in d["props"]["entry"]["fields"]')" "False"
ok "depth 0 sends related ids" "$(echo "$page" | json 'd["props"]["entry"]["fields"]["related"]')" "[10]"
ok "stale asset version gets 409" "$(curl -sk -o /dev/null -w '%{http_code}' -H 'X-Inertia: true' -H 'X-Inertia-Version: v1' "$U/articles/hello-momentum-2")" "409"
settings "['excludeFields' => null, 'propDepth' => 2, 'assetVersion' => null]"

T=$(ddev artisan tinker --execute "$(cat "$(dirname "$0")/preview-token.php")" 2>&1 | grep -o 'TOKEN=[^ ]*' | cut -d= -f2)
ok "live preview renders the draft (first visit)" "$(curl -sk "$U/articles/hello-momentum-2?token=$T" | grep -c 'DRAFT-ONLY summary')" "1"
ok "live preview renders the draft (X-Inertia)" "$(xi "$U/articles/hello-momentum-2?token=$T" | json 'd["props"]["entry"]["fields"]["summary"]')" "DRAFT-ONLY summary"
ok "preview page URL keeps the token" "$(xi "$U/articles/hello-momentum-2?token=$T" | json '"token=" in d["url"]')" "True"
ok "live page doesn't show the draft" "$(xi "$U/articles/hello-momentum-2" | json 'd["props"]["entry"]["fields"]["summary"]')" "Inertia pages from Craft routing."

ok "Twig sections are untouched" "$(curl -sk "$U/notes/a-twig-note" | grep -c 'id="twig-note"')" "1"
ok "disabled entry 404s" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/articles/second-article")" "404"
ok "unknown URI 404s" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/articles/does-not-exist")" "404"

J=$("$H/bin/cp-session")
V=$(curl -sk -b "$J" "$U/admin/dashboard" | python3 -I -c 'import sys,re,json,html;s=sys.stdin.read();m=re.search(r"<script data-page=\"app\" type=\"application/json\">(.*?)</script>",s,re.S);print(json.loads(html.unescape(m.group(1)))["version"])')
cp=$(curl -sk -b "$J" -H 'X-Inertia: true' -H "X-Inertia-Version: $V" -H 'X-Requested-With: XMLHttpRequest' "$U/admin/dashboard")
ok "CP keeps Craft's own Inertia page" "$(echo "$cp" | json 'd["component"]')" "Dashboard"
ok "CP gets no Momentum shared props" "$(echo "$cp" | json '"site" in d["props"] and "currentUser" in d["props"]')" "False"
ok "settings page loads" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' "$U/admin/settings/plugins/momentum")" "200"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
