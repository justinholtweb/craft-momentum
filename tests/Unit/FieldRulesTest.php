<?php

declare(strict_types=1);

use JustinHoltWeb\Momentum\Props\FieldRules;
use JustinHoltWeb\Momentum\Settings;

it('excludes bare handles everywhere and qualified handles on one entry type', function () {
    $rules = new FieldRules(exclude: ['internalNotes', 'article.seo']);

    expect($rules->excludes('internalNotes', null))->toBeTrue()
        ->and($rules->excludes('internalNotes', 'news'))->toBeTrue()
        ->and($rules->excludes('seo', 'article'))->toBeTrue()
        ->and($rules->excludes('seo', 'news'))->toBeFalse()
        ->and($rules->excludes('seo', null))->toBeFalse();
});

it('renames with qualified rules winning over bare ones', function () {
    $rules = new FieldRules(rename: FieldRules::parseRenames(['summary: excerpt', 'article.summary => standfirst']));

    expect($rules->propName('summary', 'news'))->toBe('excerpt')
        ->and($rules->propName('summary', 'article'))->toBe('standfirst')
        ->and($rules->propName('body', 'article'))->toBe('body');
});

it('skips rename lines that are not handle: propName', function () {
    expect(FieldRules::parseRenames(['ok: fine', 'no colon', 'a: 9starts-with-digit', 'a.b.c: x', 'x: has space']))
        ->toBe(['ok' => 'fine']);
});

it('only defers fields in Pro', function () {
    $settings = new Settings;
    $settings->deferFields = "related\narticle.gallery";

    expect($settings->fieldRules(pro: false)->defers('related', null))->toBeFalse()
        ->and($settings->fieldRules(pro: true)->defers('related', null))->toBeTrue()
        ->and($settings->fieldRules(pro: true)->defers('gallery', 'article'))->toBeTrue()
        ->and($settings->fieldRules(pro: true)->defers('gallery', 'news'))->toBeFalse();
});

it('reads renames one per line (commas are not separators)', function () {
    $settings = new Settings;
    $settings->renameFields = "summary: excerpt\nbody: content";

    expect($settings->fieldRules(pro: false)->propName('body', null))->toBe('content');
});
