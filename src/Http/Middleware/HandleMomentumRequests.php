<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Http\Middleware;

use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Http\Request;
use Inertia\Middleware;
use JustinHoltWeb\Momentum\Momentum;
use JustinHoltWeb\Momentum\Settings;
use Override;

use function CraftCms\Cms\currentUserElement;

/**
 * Inertia's protocol for front-end (site) requests: asset-version checks, 303
 * redirects and shared props. Pushed onto the `craft.web` group only, so the
 * control panel keeps Craft's own HandleInertiaRequests.
 */
class HandleMomentumRequests extends Middleware
{
    protected $rootView = Momentum::ROOT_VIEW;

    public function __construct(private readonly Settings $settings) {}

    #[Override]
    public function version(Request $request): ?string
    {
        return $this->settings->assetVersion ?: parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        $shared = [
            ...parent::share($request),
            'site' => function (): array {
                $site = Sites::getCurrentSite();

                return [
                    'handle' => $site->handle,
                    'name' => $site->getName(),
                    'language' => $site->language,
                    'baseUrl' => $site->getBaseUrl(),
                ];
            },
        ];

        if ($this->settings->shareCurrentUser) {
            $shared['currentUser'] = function (): ?array {
                $user = currentUserElement();

                return $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->getFriendlyName() ?? $user->getName(),
                ];
            };
        }

        return $shared;
    }
}
