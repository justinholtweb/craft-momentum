<?php

// Momentum harness: GraphQL schemas + tokens (idempotent). Token = "momentum{name}" padded to 32 chars.
//   full       Momentum pages, the kitchen + articles sections, the image volume, users, the default site
//   nopages    the same, without "Read Momentum page props"
//   noarticles Momentum pages + the kitchen section + site, but not the articles section, volume or users
// Run with: ddev artisan tinker --execute "require 'storage/momentum-gql-fixtures.php';"

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Data\GqlToken;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Volumes;

$site = 'sites.'.Sites::getPrimarySite()->uid.':read';
$kitchen = 'sections.'.Sections::getSectionByHandle('momentumKitchen')->uid.':read';
$articles = 'sections.'.Sections::getSectionByHandle('articles')->uid.':read';
$volume = 'volumes.'.Volumes::getVolumeByHandle('momentumImages')->uid.':read';

$schemas = [
    'full' => [$site, $kitchen, $articles, $volume, 'usergroups.everyone:read', 'momentum.pages:read'],
    'nopages' => [$site, $kitchen, $articles, $volume, 'usergroups.everyone:read'],
    'noarticles' => [$site, $kitchen, 'momentum.pages:read'],
];

foreach ($schemas as $name => $scope) {
    $schema = collect(Gql::getSchemas())->first(fn ($s) => $s->name === "momentum_{$name}") ?? new GqlSchema;
    $schema->name = "momentum_{$name}";
    $schema->scope = $scope;
    Gql::saveSchema($schema) || throw new RuntimeException(json_encode($schema->errors()->all()));

    $token = collect(Gql::getTokens())->first(fn ($t) => $t->name === "momentum_{$name}") ?? new GqlToken;
    $token->name = "momentum_{$name}";
    $token->schemaId = $schema->id;
    $token->accessToken = str_pad("momentum{$name}", 32, '0');
    Gql::saveToken($token) || throw new RuntimeException(json_encode($token->errors()->all()));
}

Gql::invalidateCaches();
echo 'ok';
