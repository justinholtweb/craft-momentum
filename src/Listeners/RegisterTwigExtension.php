<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Listeners;

use CraftCms\Cms\Twig\Events\TwigCreated;
use CraftCms\Cms\View\TemplateMode;
use JustinHoltWeb\Momentum\Twig\MomentumTwigExtension;

class RegisterTwigExtension
{
    public function handle(TwigCreated $event): void
    {
        if ($event->templateMode === TemplateMode::Site) {
            $event->twig->addExtension(new MomentumTwigExtension);
        }
    }
}
