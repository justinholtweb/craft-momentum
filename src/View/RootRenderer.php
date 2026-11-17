<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\View;

use CraftCms\Cms\Support\Facades\Template;
use CraftCms\Cms\View\TemplateMode;
use JustinHoltWeb\Momentum\Momentum;
use JustinHoltWeb\Momentum\Settings;

/**
 * Renders the site's Twig root template for a first (non-XHR) Inertia visit, as a
 * page template so Craft's head/body HTML and live-preview assets still apply.
 */
class RootRenderer
{
    public function __construct(
        private readonly Momentum $momentum,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $page
     */
    public function render(array $page): string
    {
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
