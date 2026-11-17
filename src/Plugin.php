<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use CraftCms\Cms\Element\Events\SetRoute;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Twig\Events\TwigCreated;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Illuminate\Routing\Router;
use JustinHoltWeb\Momentum\Http\Middleware\HandleMomentumRequests;
use Override;

use function CraftCms\Cms\t;

/**
 * Momentum — render Craft entries as Inertia pages (Vue, React or Svelte)
 * straight from Craft routing. See PLAN.md for the build phases.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const string EDITION_LITE = 'lite';

    public const string EDITION_PRO = 'pro';

    public bool $hasCpSettings = true;

    protected array $events = [
        SetRoute::class => Listeners\RouteInertiaEntries::class,
        TwigCreated::class => Listeners\RegisterTwigExtension::class,
    ];

    /** `_momentum/root` is the default root template. */
    protected array $siteTemplateRoots = [
        '_momentum' => __DIR__.'/../resources/site',
    ];

    #[Override]
    public static function editions(): array
    {
        return [self::EDITION_LITE, self::EDITION_PRO];
    }

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn (): Settings => $this->getSettings());
        $this->app->singleton(Momentum::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'momentum');

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('momentum', HandleMomentumRequests::class);
        // Site requests only; the control panel keeps Craft's own Inertia middleware.
        $router->pushMiddlewareToGroup('craft.web', HandleMomentumRequests::class);
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Root template', category: 'momentum'), Text::make('rootTemplate')->placeholder('_momentum/root'))
                ->instructions(t('Site template that outputs `{{ inertia(page) }}`. Leave blank for Momentum’s default.', category: 'momentum')),
            Field::make(t('Vite entry points', category: 'momentum'), Textarea::make('viteEntries'))
                ->instructions(t('Loaded by the default root template, one per line.', category: 'momentum')),
            Field::make(t('Relation depth', category: 'momentum'), Number::make('propDepth'))
                ->instructions(t('How many relation hops are serialised into page props. Deeper related elements are sent as ids.', category: 'momentum')),
            Field::make(t('Excluded fields', category: 'momentum'), Textarea::make('excludeFields'))
                ->instructions(t('Field handles never sent to the browser, one per line. Everything else in a page’s props is public.', category: 'momentum')),
            Field::make(t('Share the current user', category: 'momentum'), Lightswitch::make('shareCurrentUser'))
                ->instructions(t('Adds the logged-in user’s id and name to every page.', category: 'momentum')),
            Field::make(t('Asset version', category: 'momentum'), Text::make('assetVersion'))
                ->instructions(t('Overrides the Inertia asset version. Default: a hash of public/build/manifest.json.', category: 'momentum')),
        ]);
    }
}
