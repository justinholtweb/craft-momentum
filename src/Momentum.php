<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use CraftCms\Cms\Entry\Elements\Entry;
use Illuminate\Contracts\Events\Dispatcher;
use Inertia\Inertia;
use Inertia\Response;
use JustinHoltWeb\Momentum\Events\EntryPropsResolving;
use JustinHoltWeb\Momentum\Props\PropSerializer;

/**
 * Renders front-end Inertia pages. Use it from your own routes too:
 *
 * ```php
 * Route::get('search', fn (Momentum $momentum) => $momentum->render('Search', [...]))
 *     ->middleware(['web', 'craft', 'craft.web']);
 * ```
 */
class Momentum
{
    /** Blade view that hands the page over to the Twig root template. */
    public const string ROOT_VIEW = 'momentum::root';

    public function __construct(
        private readonly Settings $settings,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $props
     */
    public function render(string $component, array $props = []): Response
    {
        return Inertia::render($component, $props)->rootView(self::ROOT_VIEW);
    }

    public function renderEntry(string $component, Entry $entry): Response
    {
        $props = ['entry' => $this->serializer()->element($entry)];

        $this->events->dispatch($event = new EntryPropsResolving($entry, $component, $props));

        return $this->render($event->component, $event->props);
    }

    public function serializer(): PropSerializer
    {
        return new PropSerializer(
            depth: max(0, $this->settings->propDepth),
            excludeFields: $this->settings->excludedFieldHandles(),
        );
    }

    public function rootTemplate(): string
    {
        return $this->settings->rootTemplate ?: '_momentum/root';
    }
}
