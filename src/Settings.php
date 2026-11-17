<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Root view. The Twig or Blade template that holds the Inertia root element on the front end. Separate from the control panel's own Inertia root. */
    public ?string $rootView = 'app';

    /** Relation depth. How many levels of related elements are serialised into page props before they become lazy props. */
    public int $propDepth = 2;

    /** Share the current user. Adds the logged-in user (id, name, groups) to every page as a shared prop. */
    public bool $shareCurrentUser = true;
}
