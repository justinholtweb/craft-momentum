<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Listeners;

use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use JustinHoltWeb\Momentum\Momentum;

/**
 * `momentum` global for Twig and Blade alike (Craft feeds template globals to
 * both): `momentum.props(entry)`, `momentum.componentFor(entry)`.
 */
class RegisterTemplateApi
{
    public function handle(TemplateGlobalsResolving $event): void
    {
        $event->globals['momentum'] = app(Momentum::class);
    }
}
