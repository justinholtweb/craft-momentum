<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Fired before an entry is rendered as an Inertia page. Listeners may change the
 * component or add, remove and rewrite props (everything here becomes public JSON).
 */
class EntryPropsResolving
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly Entry $entry,
        public string $component,
        public array $props,
    ) {}
}
