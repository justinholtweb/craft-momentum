<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Twig;

use Inertia\Ssr\SsrState;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `{{ inertia(page) }}` and `{{ inertiaHead(page) }}` — the Twig equivalents of
 * Inertia's `@inertia` and `@inertiaHead` Blade directives, for root templates.
 * Both output the same markup as the Blade directives (and the same SSR result).
 */
class MomentumTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('inertia', $this->root(...), ['is_safe' => ['html']]),
            new TwigFunction('inertiaHead', $this->head(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public function root(array $page, string $id = 'app'): string
    {
        $ssr = app(SsrState::class)->setPage($page)->dispatch();

        if ($ssr) {
            return $ssr->body;
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return '<script data-page="'.$id.'" type="application/json">'
            .json_encode($page, JSON_HEX_TAG | JSON_THROW_ON_ERROR)
            .'</script><div id="'.$id.'"></div>';
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public function head(array $page): string
    {
        return app(SsrState::class)->setPage($page)->dispatch()->head ?? '';
    }
}
