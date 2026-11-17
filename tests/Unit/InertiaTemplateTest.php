<?php

declare(strict_types=1);

use JustinHoltWeb\Momentum\Routing\InertiaTemplate;

it('reads the component from an inertia: template', function (string $template, string $component) {
    expect(InertiaTemplate::component($template))->toBe($component);
})->with([
    ['inertia:Pages/Article', 'Pages/Article'],
    ['  inertia:Pages/Article  ', 'Pages/Article'],
    ['INERTIA:Home', 'Home'],
    ['inertia:/blog/show/', 'blog/show'],
    ['inertia:pages/news-item_v2', 'pages/news-item_v2'],
]);

it('ignores ordinary Twig templates and unsafe component names', function (?string $template) {
    expect(InertiaTemplate::component($template))->toBeNull();
})->with([
    null,
    '',
    'blog/_entry',
    'inertia:',
    'inertia:../secrets',
    'inertia:Pages/Article.vue',
    'inertia:Pages//Article',
    'inertia:Pages/<script>',
]);
