<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Ssr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\Response;
use JustinHoltWeb\Momentum\Plugin;
use JustinHoltWeb\Momentum\Settings;
use Throwable;

/**
 * Pro: sends a first visit's page to the Inertia SSR server (`node bootstrap/ssr/ssr.js`
 * or `php artisan inertia:start-ssr`) and returns its head + body. Any failure falls back
 * to client-side rendering, so a stopped SSR server never takes the site down.
 *
 * Momentum owns SSR for site pages; Inertia's global `inertia.ssr.enabled` (which the
 * control panel would see too) is left alone.
 */
class SsrRenderer
{
    public function __construct(private readonly Settings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->ssr && Plugin::isPro();
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public function render(array $page): ?Response
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout((int) (config('inertia.ssr.timeout') ?: 5))
                ->acceptJson()
                ->post($this->url().'/render', $page);
        } catch (Throwable $e) {
            Log::warning('Momentum: the SSR server could not be reached; rendering client-side.', ['error' => $e->getMessage()]);

            return null;
        }

        $data = $response->successful() ? $response->json() : null;

        if (! is_array($data) || ! isset($data['body']) || ! is_string($data['body'])) {
            Log::warning('Momentum: the SSR server returned no page; rendering client-side.', ['status' => $response->status()]);

            return null;
        }

        $head = array_filter((array) ($data['head'] ?? []), is_string(...));

        return new Response(implode("\n", $head), $data['body']);
    }

    public function url(): string
    {
        $url = $this->settings->ssrUrl ?: config('inertia.ssr.url', 'http://127.0.0.1:13714');

        return rtrim(is_string($url) ? $url : 'http://127.0.0.1:13714', '/');
    }
}
