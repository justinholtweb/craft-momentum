<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Gql;

use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Queries\Query;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type;

/**
 * `momentumPage` — an Inertia section entry's component and page props, for
 * schemas given “Read Momentum page props”. Props are the same JSON an Inertia
 * visit gets (without the shared `site`/`currentUser`/`errors`), limited to what
 * the schema can read: related entries, assets and users outside its scope are
 * left out, as are other element types.
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 */
class PageQuery extends Query
{
    /** @return array<string, UnnamedFieldDefinitionConfig> */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && ! GqlHelper::canSchema(PageResolver::COMPONENT)) {
            return [];
        }

        return [
            'momentumPage' => [
                'type' => PageType::getType(),
                'args' => [
                    'id' => ['name' => 'id', 'type' => Type::id(), 'description' => 'The entry’s id.'],
                    'uri' => ['name' => 'uri', 'type' => Type::string(), 'description' => 'The entry’s URI on the site (e.g. `articles/hello`). Use `__home__` for the homepage.'],
                    'site' => ['name' => 'site', 'type' => Type::string(), 'description' => 'A site handle (default: the primary site the schema can read).'],
                ],
                'resolve' => [PageResolver::class, 'resolve'],
                'description' => 'An Inertia section entry rendered as Momentum page props. Null when the entry doesn’t exist, isn’t live, isn’t readable by this schema, or its section doesn’t use an `inertia:` template on that site.',
                'complexity' => GqlHelper::relatedArgumentComplexity(),
            ],
        ];
    }
}
