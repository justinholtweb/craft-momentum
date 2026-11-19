<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use JustinHoltWeb\Momentum\Gql\PageResolver;

use function CraftCms\Cms\t;

/** “Read Momentum page props” in the GraphQL schema editor. */
class RegisterGqlComponents
{
    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $event->queries['Momentum'] = [
            PageResolver::COMPONENT.':read' => [
                'label' => t('Read Momentum page props', category: 'momentum'),
                'info' => t('Adds the `momentumPage` query. Only entries, assets and users this schema can also read are included.', category: 'momentum'),
                'authenticated' => true,
            ],
        ];
    }
}
