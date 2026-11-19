<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\View;

use CraftCms\Cms\Support\Facades\Template;
use CraftCms\Cms\View\TemplateMode;
use Inertia\Ssr\SsrState;
use JustinHoltWeb\Momentum\Momentum;
use JustinHoltWeb\Momentum\Settings;
use JustinHoltWeb\Momentum\Ssr\MomentumSsrState;
use JustinHoltWeb\Momentum\Ssr\SsrRenderer;

/**
 * Renders the site's root template (Twig or Blade, by extension) for a first
 * (non-XHR) Inertia visit, as a page template so Craft's head/body HTML and
 * live-preview assets still apply.
 */
class RootRenderer
{
    public function __construct(
        private readonly Momentum $momentum,
        private readonly Settings $settings,
        private readonly SsrRenderer $ssr,
    ) {}

    /**
     * @param  array<string, mixed>  $page
     */
    public function render(array $page): string
    {
        // Site pages only (this view is Momentum's root): SSR comes from Momentum, never Inertia's global gateway.
        app()->instance(SsrState::class, new MomentumSsrState($this->ssr));

        return Template::renderPageTemplate(
            $this->momentum->rootTemplate(),
            [
                'page' => $page,
                'viteEntries' => $this->settings->viteEntryList(),
            ],
            TemplateMode::Site,
        );
    }
}
