<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum;

use Closure;
use CraftCms\Cms\Plugin\PluginSettings;
use JustinHoltWeb\Momentum\Props\FieldRules;

use function CraftCms\Cms\t;

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
     * Field handles never sent to the browser, one per line. `handle` everywhere, or
     * `entryType.handle` (`volume.handle` for assets) for one entry type.
     *
     * @var string|list<string>|null
     */
    public string|array|null $excludeFields = null;

    /**
     * Fields sent under another prop name, one `handle: propName` per line (`entryType.handle: propName` for one entry type).
     *
     * @var string|list<string>|null
     */
    public string|array|null $renameFields = null;

    /**
     * Named image transforms added to every image asset as `transforms.{handle}`, one per line.
     *
     * @var string|list<string>|null
     */
    public string|array|null $assetTransforms = null;

    /** Share the logged-in user's id and name with every page. */
    public bool $shareCurrentUser = true;

    /** Override the Inertia asset version (default: hash of public/build/manifest.json). */
    public ?string $assetVersion = null;

    /**
     * Pro: fields sent as Inertia deferred props (loaded by a follow-up request after the page renders),
     * one `handle` or `entryType.handle` per line.
     *
     * @var string|list<string>|null
     */
    public string|array|null $deferFields = null;

    /** Pro: render first visits on an Inertia SSR server. */
    public bool $ssr = false;

    /** Pro: the SSR server's base URL. Null uses Inertia's `inertia.ssr.url` (default http://127.0.0.1:13714). */
    public ?string $ssrUrl = null;

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
     * @return list<string>
     */
    public function transformHandles(): array
    {
        return self::lines($this->assetTransforms);
    }

    public function fieldRules(bool $pro): FieldRules
    {
        return new FieldRules(
            exclude: self::lines($this->excludeFields),
            rename: FieldRules::parseRenames(self::lines($this->renameFields, commas: false)),
            defer: $pro ? self::lines($this->deferFields) : [],
        );
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    public function getRules(): array
    {
        $handles = function (string $attribute, mixed $value, Closure $fail): void {
            foreach (self::lines($value) as $line) {
                if (! preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', $line)) {
                    $fail(t('“{handle}” isn’t a field handle (use `handle` or `entryType.handle`).', ['handle' => $line], category: 'momentum'));
                }
            }
        };

        return [
            'propDepth' => ['required', 'integer', 'min:0', 'max:5'],
            'excludeFields' => [$handles],
            'deferFields' => [$handles],
            'renameFields' => [function (string $attribute, mixed $value, Closure $fail): void {
                $lines = self::lines($value, commas: false);

                if (count(FieldRules::parseRenames($lines)) !== count($lines)) {
                    $fail(t('Write each rename as `handle: propName` (prop names are letters, numbers and underscores).', category: 'momentum'));
                }
            }],
            'assetTransforms' => [function (string $attribute, mixed $value, Closure $fail): void {
                foreach (self::lines($value) as $line) {
                    if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $line)) {
                        $fail(t('“{handle}” isn’t an image transform handle.', ['handle' => $line], category: 'momentum'));
                    }
                }
            }],
            'ssrUrl' => ['nullable', 'url:http,https', 'max:255'],
            'assetVersion' => ['nullable', 'string', 'max:100'],
            'rootTemplate' => ['nullable', 'string', 'max:255', 'not_regex:/(^|\/)\.\.(\/|$)/'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function lines(mixed $value, bool $commas = true): array
    {
        $items = is_array($value) ? $value : preg_split($commas ? '/[\r\n,]+/' : '/[\r\n]+/', (string) $value);

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items ?: []), fn (string $v) => $v !== ''));
    }
}
