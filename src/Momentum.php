<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use Closure;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Contracts\Events\Dispatcher;
use Inertia\Inertia;
use Inertia\Response;
use JustinHoltWeb\Momentum\Events\EntryPropsResolving;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Props\Transformers\Transformers;
use JustinHoltWeb\Momentum\Routing\InertiaTemplate;

/**
 * Renders front-end Inertia pages. Use it from your own routes too:
 *
 * ```php
 * Route::get('search', fn (Momentum $momentum) => $momentum->render('Search', [...]))
 *     ->middleware(['web', 'craft', 'craft.web']);
 * ```
 *
 * In templates (Twig and Blade alike) it's the `momentum` global:
 * `momentum.props(entry)` returns the same props an Inertia page would get.
 */
class Momentum
{
    /** Blade view that hands the page over to the site's root template. */
    public const string ROOT_VIEW = 'momentum::root';

    public function __construct(
        private readonly Settings $settings,
        private readonly Dispatcher $events,
        private readonly Transformers $transformers,
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
        [$component, $props] = $this->entryPage($component, $entry, $this->serializer(defer: true));

        return $this->render($component, $props);
    }

    /**
     * The component and page props for an entry, after `EntryPropsResolving`.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function entryPage(string $component, Entry $entry, PropSerializer $serializer, string $context = EntryPropsResolving::CONTEXT_PAGE): array
    {
        $props = [
            'entry' => $serializer->root($entry),
            'alternates' => $this->alternates($entry),
        ];

        $this->events->dispatch($event = new EntryPropsResolving($entry, $component, $props, $context));

        return [$event->component, $event->props];
    }

    /**
     * The props an entry's Inertia page gets (without shared props), for use in
     * templates: `{{ momentum.props(entry)|json_encode }}`.
     *
     * @return array<string, mixed>
     */
    public function props(Entry $entry): array
    {
        return $this->entryPage($this->componentFor($entry) ?? '', $entry, $this->serializer())[1];
    }

    /**
     * The entry's Inertia component on its site, or null when its section
     * renders a Twig/Blade template.
     */
    public function componentFor(Entry $entry): ?string
    {
        $settings = $entry->getSection()?->getSiteSettings()[$entry->siteId] ?? null;

        return $settings?->hasUrls ? InertiaTemplate::component($settings->template) : null;
    }

    /**
     * @param  (Closure(ElementInterface): bool)|null  $allow
     * @param  (Closure(FieldInterface, ElementInterface): bool)|null  $fieldVisible
     */
    public function serializer(bool $defer = false, ?Closure $allow = null, ?Closure $fieldVisible = null): PropSerializer
    {
        $pro = Plugin::isPro();

        return new PropSerializer(
            depth: max(0, $this->settings->propDepth),
            rules: $this->settings->fieldRules($pro),
            transformers: $this->transformers,
            assetTransforms: $this->settings->transformHandles(),
            allow: $allow,
            fieldVisible: $fieldVisible,
            defer: $defer && $pro,
        );
    }

    /**
     * The entry on every site it's live on, for language switchers and
     * `hreflang` links. One query.
     *
     * @return list<array{site: string, language: string, url: string, current: bool}>
     */
    public function alternates(Entry $entry): array
    {
        if ($entry->id === null || Sites::getAllSites()->count() < 2) {
            return [];
        }

        $alternates = [];
        $others = Entry::find()->id($entry->getCanonicalId())->site('*')->collect();

        foreach ($others as $other) {
            $url = $other->getUrl();
            $site = $other->getSite();

            if ($url !== null && $site->enabled) {
                $alternates[] = [
                    'site' => $site->handle,
                    'language' => $site->language,
                    'url' => $other->siteId === $entry->siteId ? ($entry->getUrl() ?? $url) : $url,
                    'current' => $other->siteId === $entry->siteId,
                ];
            }
        }

        // Only worth sending when there's somewhere else to go.
        return count($alternates) > 1 ? $alternates : [];
    }

    public function rootTemplate(): string
    {
        return $this->settings->rootTemplate ?: '_momentum/root';
    }
}
