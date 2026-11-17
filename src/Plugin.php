<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Override;

use function CraftCms\Cms\t;

/**
 * Momentum — Render Craft entries as Inertia pages (Vue, React or Svelte) straight from Craft routing — no headless API, no second router.
 *
 * See PLAN.md for the build phases.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    public const string EDITION_LITE = 'lite';

    public const string EDITION_PRO = 'pro';

    #[Override]
    public static function editions(): array
    {
        return [self::EDITION_LITE, self::EDITION_PRO];
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
            Field::make(t('Root view', category: 'momentum'), Text::make('rootView'))
                ->instructions(t('The Twig or Blade template that holds the Inertia root element on the front end. Separate from the control panel\'s own Inertia root.', category: 'momentum')),
            Field::make(t('Relation depth', category: 'momentum'), Number::make('propDepth'))
                ->instructions(t('How many levels of related elements are serialised into page props before they become lazy props.', category: 'momentum')),
            Field::make(t('Share the current user', category: 'momentum'), Lightswitch::make('shareCurrentUser'))
                ->instructions(t('Adds the logged-in user (id, name, groups) to every page as a shared prop.', category: 'momentum')),
        ]);
    }
}
