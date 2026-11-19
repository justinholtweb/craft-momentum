<?php

// Removes everything fixtures.php, gql-fixtures.php and multisite-fixtures.php created (all `momentum`-prefixed).
// Run with: ddev artisan tinker --execute "require 'storage/momentum-cleanup.php';"

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\ImageTransforms;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\User\Elements\User;

foreach (Entry::find()->section('momentumKitchen')->status(null)->site('*')->unique()->all() as $e) {
    Elements::deleteElement($e, true);
}
if ($s = Sections::getSectionByHandle('momentumKitchen')) {
    Sections::deleteSection($s);
}
foreach (['momentumKitchen', 'momentumText', 'momentumGallery'] as $h) {
    if ($t = EntryTypes::getEntryTypeByHandle($h)) {
        EntryTypes::deleteEntryType($t);
    }
}
foreach (Fields::getAllFields() as $f) {
    if (str_starts_with((string) $f->handle, 'momentum')) {
        Fields::deleteField($f);
    }
}
foreach (Asset::find()->volume('momentumImages')->status(null)->all() as $a) {
    Elements::deleteElement($a, true);
}
if ($v = Volumes::getVolumeByHandle('momentumImages')) {
    Volumes::deleteVolume($v);
}
if ($t = ImageTransforms::getTransformByHandle('momentumThumb')) {
    ImageTransforms::deleteTransform($t);
}
if ($u = User::find()->username('momentumauthor')->status(null)->one()) {
    Elements::deleteElement($u, true);
}
foreach (Gql::getTokens() as $t) {
    if (str_starts_with((string) $t->name, 'momentum_')) {
        Gql::deleteTokenById($t->id);
    }
}
foreach (Gql::getSchemas() as $s) {
    if (str_starts_with((string) $s->name, 'momentum_')) {
        Gql::deleteSchemaById($s->id);
    }
}
Gql::invalidateCaches();
echo 'cleaned';
