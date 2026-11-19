<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Gql;

use Closure;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Resolvers\Elements\Asset as AssetResolver;
use CraftCms\Cms\Gql\Resolvers\Elements\Entry as EntryResolver;
use CraftCms\Cms\Gql\Resolvers\Elements\User as UserResolver;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\User\Elements\User;
use GraphQL\Error\UserError;
use JustinHoltWeb\Momentum\Events\EntryPropsResolving;
use JustinHoltWeb\Momentum\Momentum;

class PageResolver
{
    public const string COMPONENT = 'momentum.pages';

    /**
     * @param  array<string, mixed>  $args
     * @return array{component: string, url: string|null, site: string, props: string}|null
     */
    public static function resolve(mixed $source, array $args): ?array
    {
        if (! GqlHelper::canSchema(self::COMPONENT)) {
            throw new UserError('This schema can’t read Momentum page props.');
        }

        $site = self::site(isset($args['site']) ? (string) $args['site'] : null);
        $id = $args['id'] ?? null;
        $uri = isset($args['uri']) ? trim((string) $args['uri'], '/') : null;

        if (($id === null) === ($uri === null)) {
            throw new UserError('Pass either `id` or `uri`.');
        }

        $query = GqlHelper::canQueryEntries() ? self::scoped(EntryResolver::prepareQuery(null, []), $site) : null;

        if ($query === null) {
            return null;
        }

        $entry = $id !== null
            ? $query->id((int) $id)->one()
            : $query->uri($uri === '' ? '__home__' : $uri)->one();

        $momentum = app(Momentum::class);

        if (! $entry instanceof Entry || ($component = $momentum->componentFor($entry)) === null) {
            return null;
        }

        [$component, $props] = $momentum->entryPage(
            $component,
            $entry,
            $momentum->serializer(allow: self::gate($site), fieldVisible: self::fieldGate()),
            EntryPropsResolving::CONTEXT_GRAPHQL,
        );

        // Only the sites this schema can read.
        $readable = array_map(fn (Site $s) => $s->handle, GqlHelper::getAllowedSites());
        $props['alternates'] = array_values(array_filter(
            (array) ($props['alternates'] ?? []),
            fn ($a) => is_array($a) && in_array($a['site'] ?? null, $readable, true),
        ));

        return [
            'component' => $component,
            'url' => $entry->getUrl(),
            'site' => $site->handle,
            'props' => json_encode($props, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Related (non-nested) elements are kept only when this schema could fetch
     * them itself with `entries`, `assets` or `users` (which also applies a
     * token user's view permissions). Other element types are left out.
     *
     * @return Closure(ElementInterface): bool
     */
    private static function gate(Site $site): Closure
    {
        $memo = [];

        return function (ElementInterface $element) use (&$memo, $site): bool {
            // Craft's resolvers assume the schema can query their element type, so check that first.
            $resolver = match (true) {
                $element instanceof Entry => GqlHelper::canQueryEntries() ? EntryResolver::class : null,
                $element instanceof Asset => GqlHelper::canQueryAssets() ? AssetResolver::class : null,
                $element instanceof User => GqlHelper::canQueryUsers() ? UserResolver::class : null,
                default => null,
            };

            if ($resolver === null || $element->id === null) {
                return false;
            }

            $key = $resolver.':'.$element->id.':'.$element->siteId;

            return $memo[$key] ??= (bool) self::scoped($resolver::prepareQuery(null, []), $site, $element->siteId)?->id($element->id)->exists();
        };
    }

    /**
     * With a token tied to a user, fields that user couldn't see are left out.
     *
     * @return (Closure(FieldInterface, ElementInterface): bool)|null
     */
    private static function fieldGate(): ?Closure
    {
        $user = GqlHelper::getSchemaUser();

        return $user === null ? null : fn (FieldInterface $field) => GqlHelper::isFieldVisibleToUser($field, $user);
    }

    /**
     * @return ElementQueryInterface<covariant ElementInterface>|null
     */
    private static function scoped(mixed $query, Site $site, ?int $siteId = null): ?ElementQueryInterface
    {
        if (! $query instanceof ElementQueryInterface) {
            return null;
        }

        return $siteId !== null ? $query->siteId($siteId) : $query->site($site->handle);
    }

    /** The requested site, or the primary one the schema can read. */
    private static function site(?string $handle): Site
    {
        $allowed = GqlHelper::getAllowedSites();

        foreach ($allowed as $site) {
            if ($handle === null ? $site->primary : $site->handle === $handle) {
                return $site;
            }
        }

        if ($handle === null && $allowed !== []) {
            return $allowed[0];
        }

        // Unknown and not-allowed read the same.
        throw new UserError($handle === null ? 'This schema can’t read any site.' : "This schema can’t read the “{$handle}” site.");
    }
}
