use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\Routing\ActionRoute;
use CraftCms\Cms\Support\Facades\Elements;
$canonical = Entry::find()->slug('hello-momentum-2')->one();
$admin = \CraftCms\Cms\User\Elements\User::find()->admin()->one();
$draft = app(\CraftCms\Cms\Element\Drafts::class)->createDraft($canonical, $admin->id, 'Momentum preview test');
$draft->setFieldValue('summary', 'DRAFT-ONLY summary');
Elements::saveElement($draft);
$route = [ActionRoute::uriForSegments(['preview', 'preview'], false), [
    'elementType' => Entry::class, 'canonicalId' => $canonical->id, 'siteId' => $canonical->siteId,
    'draftId' => $draft->draftId, 'revisionId' => null, 'userId' => $admin->id,
]];
echo 'TOKEN=', app(\CraftCms\Cms\RouteToken\RouteTokens::class)->createPreviewToken($route), "\n";
