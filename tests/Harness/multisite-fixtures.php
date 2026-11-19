<?php

// Momentum harness: a second site, `momentumfr` (French, /momentumfr/), owned by Momentum's checks
// (other agents add and remove their own sites; never touch theirs). The kitchen section gets
// French URIs (`cuisine/{slug}`) and the kitchen sink a French title. Idempotent.
// Remove with multisite-cleanup.php (under bin/harness-lock).
// Run with: ddev artisan tinker --execute "require 'storage/momentum-multisite-fixtures.php';"

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;

$fr = Sites::getSiteByHandle('momentumfr');

if (! $fr) {
    $primary = Sites::getPrimarySite();
    $fr = new Site;
    $fr->groupId = $primary->groupId;
    $fr->name = 'Momentum Français';
    $fr->handle = 'momentumfr';
    $fr->language = 'fr-FR';
    $fr->hasUrls = true;
    $fr->baseUrl = '$APP_URL/momentumfr/'; // Craft 6 parses env vars, not Craft 5's @web alias
    $fr->enabled = true;
    Sites::saveSite($fr) || throw new RuntimeException('site: '.json_encode($fr->errors()->all()));
}

$section = Sections::getSectionByHandle('momentumKitchen');
$settings = $section->getSiteSettings();

if (! isset($settings[$fr->id])) {
    $s = new SectionSiteSettings;
    $s->siteId = $fr->id;
    $s->hasUrls = true;
    $s->uriFormat = 'cuisine/{slug}';
    $s->template = 'inertia:Pages/Kitchen';
    $s->enabledByDefault = true;
    $settings[$fr->id] = $s;
    $section->setSiteSettings($settings);
    Sections::saveSection($section) || throw new RuntimeException('section: '.json_encode($section->errors()->all()));
}

// Saving the section with a new site didn't queue a resave in beta.1 (default queue stayed empty),
// so propagate the kitchen entries by resaving them; saving creates their rows on the new site.
foreach (Entry::find()->section('momentumKitchen')->status(null)->all() as $e) {
    if (! Entry::find()->id($e->id)->site('momentumfr')->status(null)->exists()) {
        Elements::saveElement($e);
    }
}

$out = ['site' => $fr->id];
foreach (['momentum-kitchen-sink' => 'Évier de cuisine', 'momentum-related-a' => 'Lié A'] as $slug => $title) {
    $entry = Entry::find()->section('momentumKitchen')->slug($slug)->site('momentumfr')->status(null)->one()
        ?? throw new RuntimeException("{$slug} did not propagate to momentumfr (run the queue)");
    $entry->title = $title;
    // Relations are stored per site (sourceSiteId) and a resave-propagated entry doesn't copy them.
    $entry->setFieldValue('momentumRelated', Entry::find()->section('momentumKitchen')->site('momentumfr')
        ->slug($slug === 'momentum-kitchen-sink' ? 'momentum-related-a' : 'momentum-related-b')->status(null)->ids());
    Elements::saveElement($entry) || throw new RuntimeException('entry: '.json_encode($entry->errors()->all()));
    $out[$slug] = $entry->id;
}

echo json_encode($out);
