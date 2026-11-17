<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Http\Controllers;

use CraftCms\Cms\Entry\Elements\Entry;
use Illuminate\Http\Request;
use Inertia\Response;
use JustinHoltWeb\Momentum\Momentum;

/**
 * Renders a routed entry as an Inertia page. The component comes from the
 * section's `inertia:<Component>` template setting.
 */
class EntryPageController
{
    public function __invoke(Request $request, Momentum $momentum): Response
    {
        $entry = $request->route('element');
        $component = $request->route('momentumComponent');

        abort_unless($entry instanceof Entry && is_string($component), 404);

        return $momentum->renderEntry($component, $entry);
    }
}
