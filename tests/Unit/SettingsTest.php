<?php

declare(strict_types=1);

use JustinHoltWeb\Momentum\Settings;

it('has defaults that render without a Vite build', function () {
    $settings = new Settings;

    expect($settings->rootTemplate)->toBeNull()
        ->and($settings->viteEntryList())->toBe([])
        ->and($settings->propDepth)->toBe(2)
        ->and($settings->excludedFieldHandles())->toBe([]);
});

it('splits line- or comma-separated lists from the CP', function () {
    $settings = new Settings;
    $settings->viteEntries = "resources/js/app.js\n resources/css/app.css \n\n";
    $settings->excludeFields = 'internalNotes, seo';

    expect($settings->viteEntryList())->toBe(['resources/js/app.js', 'resources/css/app.css'])
        ->and($settings->excludedFieldHandles())->toBe(['internalNotes', 'seo']);
});

it('accepts arrays from the config file', function () {
    $settings = new Settings;
    $settings->excludeFields = ['internalNotes', ' ', 'seo'];

    expect($settings->excludedFieldHandles())->toBe(['internalNotes', 'seo']);
});
