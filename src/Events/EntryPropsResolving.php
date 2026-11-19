<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Fired before an entry is rendered as an Inertia page (and when its props are
 * built for `momentum.props()` or the `momentumPage` GraphQL query). Listeners
 * may change the component or add, remove and rewrite props: everything here
 * becomes public JSON.
 */
class EntryPropsResolving
{
    /** An Inertia page response, or `momentum.props()` in a template. */
    public const string CONTEXT_PAGE = 'page';

    /** The `momentumPage` GraphQL query: add only what any schema may see. */
    public const string CONTEXT_GRAPHQL = 'graphql';

    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly Entry $entry,
        public string $component,
        public array $props,
        public readonly string $context = self::CONTEXT_PAGE,
    ) {}
}
