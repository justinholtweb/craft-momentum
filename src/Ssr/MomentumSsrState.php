<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Ssr;

use Inertia\Ssr\Response;
use Inertia\Ssr\SsrState;

/**
 * Stands in for Inertia's request-scoped SsrState while a site page renders, so
 * Twig's `inertia()` and Blade's `@inertia` both get Momentum's SSR result (or
 * none) instead of Inertia's global gateway.
 */
class MomentumSsrState extends SsrState
{
    /** @var array<string, Response|null> */
    private array $rendered = [];

    public function __construct(private readonly SsrRenderer $renderer) {}

    public function dispatch(): ?Response
    {
        $key = md5(json_encode($this->page) ?: '');

        if (! array_key_exists($key, $this->rendered)) {
            $this->rendered[$key] = $this->renderer->render($this->page);
        }

        return $this->response = $this->rendered[$key];
    }
}
