<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Site template that holds the Inertia root element. Null uses Momentum's `_momentum/root`. */
    public ?string $rootTemplate = null;

    /**
     * Vite entry points loaded by the default root template, one per line
     * (e.g. `resources/js/app.js`). Blank loads none, so a fresh install without a
     * Vite build still renders.
     *
     * @var string|list<string>|null
     */
    public string|array|null $viteEntries = null;

    /** How many relation hops are serialised into props before related elements become ids. */
    public int $propDepth = 2;

    /**
     * Field handles never sent to the browser, one per line.
     *
     * @var string|list<string>|null
     */
    public string|array|null $excludeFields = null;

    /** Share the logged-in user's id and name with every page. */
    public bool $shareCurrentUser = true;

    /** Override the Inertia asset version (default: hash of public/build/manifest.json). */
    public ?string $assetVersion = null;

    /**
     * @return list<string>
     */
    public function viteEntryList(): array
    {
        return self::lines($this->viteEntries);
    }

    /**
     * @return list<string>
     */
    public function excludedFieldHandles(): array
    {
        return self::lines($this->excludeFields);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getRules(): array
    {
        return [
            'propDepth' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }

    /**
     * @param  string|list<string>|null  $value
     * @return list<string>
     */
    private static function lines(string|array|null $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\r\n,]+/', (string) $value);

        return array_values(array_filter(array_map(trim(...), $items ?: []), fn (string $v) => $v !== ''));
    }
}
