<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Listeners;

use CraftCms\Cms\Element\Events\SetRoute;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\ControllerRoute;
use JustinHoltWeb\Momentum\Http\Controllers\EntryPageController;
use JustinHoltWeb\Momentum\Routing\InertiaTemplate;

/**
 * Sends a section entry whose template is `inertia:<Component>` to Momentum's
 * page controller instead of Twig. Mirrors Entry::route()'s own guards.
 */
class RouteInertiaEntries
{
    public function handle(SetRoute $event): void
    {
        $entry = $event->element;

        if (! $entry instanceof Entry || $event->handled || $entry->getField() !== null) {
            return;
        }

        if (! $entry->previewing && $entry->getStatus() !== Entry::STATUS_LIVE) {
            return;
        }

        $settings = $entry->getSection()?->getSiteSettings()[$entry->siteId] ?? null;

        if (! $settings?->hasUrls || $settings->route) {
            return;
        }

        $component = InertiaTemplate::component($settings->template);

        if ($component === null) {
            return;
        }

        $event->route = new ControllerRoute(EntryPageController::class, ['momentumComponent' => $component]);
        $event->handled = true;
    }
}
