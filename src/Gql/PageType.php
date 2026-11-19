<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Gql;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/** The shape `momentumPage` returns. */
class PageType
{
    public static function getType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate('MomentumPage', fn () => new ObjectType([
            'name' => 'MomentumPage',
            'description' => 'An entry as a Momentum (Inertia) page.',
            'fields' => fn () => [
                'component' => ['type' => Type::nonNull(Type::string()), 'description' => 'The Inertia component from the section’s `inertia:` template.'],
                'url' => ['type' => Type::string(), 'description' => 'The entry’s URL on the site.'],
                'site' => ['type' => Type::nonNull(Type::string()), 'description' => 'The site handle the props were built for.'],
                'props' => ['type' => Type::nonNull(Type::string()), 'description' => 'The page props as a JSON string (`JSON.parse()` it): `entry`, `alternates` and anything `EntryPropsResolving` listeners add.'],
            ],
        ]));
    }
}
