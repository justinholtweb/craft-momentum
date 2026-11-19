<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props;

use BackedEnum;
use Closure;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Eloquent\EloquentElement;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Assets as AssetsField;
use CraftCms\Cms\Field\Contracts\EagerLoadingFieldInterface;
use CraftCms\Cms\Field\Contracts\ElementContainerFieldInterface;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Elements\ContentBlock;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use JsonSerializable;
use JustinHoltWeb\Momentum\Props\Transformers\OmitTransformer;
use JustinHoltWeb\Momentum\Props\Transformers\Transformers;
use Stringable;
use Throwable;
use Traversable;
use UnitEnum;

/**
 * Turns elements into plain arrays for Inertia page props, driven by each
 * element's field layout and the field transformers.
 *
 * Everything here ends up in the page's public JSON, so the rules are
 * conservative: users expose only an id and a display name, element types we
 * don't know expose only id/title/url, Eloquent elements only their model's
 * non-`$hidden` attributes, and values we can't represent become null rather
 * than leaking an object's internals.
 *
 * Depth counts relation hops. Nested elements (Matrix entries, content blocks)
 * are part of their owner, so they don't spend depth; related elements do. At
 * depth 0 a relation becomes a list of ids.
 */
class PropSerializer
{
    private ?ElementInterface $owner = null;

    /**
     * @param  list<string>  $assetTransforms  Named image transforms added to every image asset as `transforms.{handle}`.
     * @param  (Closure(ElementInterface): bool)|null  $allow  Gate for related (non-nested) elements; denied ones are dropped.
     * @param  (Closure(FieldInterface, ElementInterface): bool)|null  $fieldVisible  Extra per-field gate.
     * @param  bool  $defer  Send fields matched by the defer rules as Inertia deferred props (Pro).
     */
    public function __construct(
        private readonly int $depth = 2,
        private readonly FieldRules $rules = new FieldRules,
        private readonly ?Transformers $transformers = null,
        private readonly array $assetTransforms = [],
        private readonly ?Closure $allow = null,
        private readonly ?Closure $fieldVisible = null,
        private readonly bool $defer = false,
        private readonly bool $eagerLoad = true,
    ) {}

    /**
     * Serialises the page's root element: eager-loads what its layout needs
     * first, and may defer fields (Pro).
     *
     * @return array<string, mixed>
     */
    public function root(ElementInterface $element): array
    {
        $this->prepare([$element], $this->depth, root: true);

        return $this->element($element, $this->depth, root: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function element(ElementInterface $element, ?int $depth = null, bool $root = false): array
    {
        $depth ??= $this->depth;

        if ($element instanceof User) {
            return ['id' => $element->id, 'name' => $element->getFriendlyName() ?? $element->getName()];
        }

        $data = [
            'id' => $element->id,
            'title' => $element->title,
            'url' => $element->getUrl(),
        ];

        if ($element instanceof Entry) {
            $data += [
                'uid' => $element->uid,
                'slug' => $element->slug,
                'uri' => $element->uri,
                'section' => $element->getSection()?->handle,
                'type' => $element->getType()->handle,
                'postDate' => $this->value($element->postDate, 0),
                'dateUpdated' => $this->value($element->dateUpdated, 0),
            ];
        } elseif ($element instanceof Asset) {
            $data += [
                'uid' => $element->uid,
                'alt' => $element->alt,
                'filename' => $element->getFilename(),
                'kind' => $element->kind,
                'mimeType' => $element->getMimeType(),
                'size' => $element->size,
                'width' => $element->getWidth(),
                'height' => $element->getHeight(),
                'focalPoint' => $element->getHasFocalPoint() ? $element->getFocalPoint() : null,
                'transforms' => $this->transforms($element),
            ];
        } elseif ($element instanceof ContentBlock) {
            $data = ['id' => $element->id];
        } elseif ($element instanceof EloquentElement) {
            $data['attributes'] = $this->modelAttributes($element) ?: new \stdClass;
        } else {
            // Unknown element types (addresses, third-party elements) can carry
            // personal data; expose only what any public link would.
            return $data;
        }

        // An empty object, not `[]`, so `fields` has one JSON type.
        $data['fields'] = $this->fields($element, $depth, $root) ?: new \stdClass;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(ElementInterface $element, int $depth, bool $root = false): array
    {
        $layout = $element->getFieldLayout();

        if ($layout === null) {
            return [];
        }

        $qualifier = self::qualifier($element);
        $fields = [];

        foreach ($layout->getVisibleCustomFields($element) as $field) {
            $handle = $field->handle;

            if ($handle === null || $this->rules->excludes($handle, $qualifier)) {
                continue;
            }

            if ($this->fieldVisible !== null && ! ($this->fieldVisible)($field, $element)) {
                continue;
            }

            $name = $this->rules->propName($handle, $qualifier);

            if ($root && $this->defer && $this->rules->defers($handle, $qualifier)) {
                $fields[$name] = Inertia::defer(function () use ($element, $field, $depth) {
                    $this->prepare([$element], $depth, only: [$field->handle]);

                    $value = $this->fieldValue($element, $field, $depth);

                    return $value === Omit::Field ? null : $value;
                }, 'momentum');

                continue;
            }

            $value = $this->fieldValue($element, $field, $depth);

            if ($value !== Omit::Field) {
                $fields[$name] = $value;
            }
        }

        return $fields;
    }

    public function fieldValue(ElementInterface $element, FieldInterface $field, int $depth): mixed
    {
        $value = $element->getFieldValue((string) $field->handle);
        $transformer = $this->transformers?->for($field);
        $previous = $this->owner;
        $this->owner = $element;

        try {
            return $transformer !== null
                ? $transformer->transform($field, $value, $this, $depth)
                : $this->value($value, $depth);
        } finally {
            $this->owner = $previous;
        }
    }

    /**
     * The element whose field is being transformed, so a transformer can tell
     * the owner's own nested elements from anything else a value points at.
     */
    public function owner(): ?ElementInterface
    {
        return $this->owner;
    }

    public function value(mixed $value, int $depth): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof ElementInterface => $this->one($value, $depth),
            // Unknown field types: any elements in their values are treated as relations.
            $value instanceof ElementQueryInterface => $this->relations($value, $depth),
            // Before Arrayable: a collection's toArray() would serialise elements wholesale.
            $value instanceof Collection => $this->list($value->all(), $depth),
            $value instanceof Htmlable => $value->toHtml(),
            $value instanceof Stringable => (string) $value,
            $value instanceof JsonSerializable => $this->value($value->jsonSerialize(), $depth),
            $value instanceof Arrayable => $this->value($value->toArray(), $depth),
            is_array($value) => $this->list($value, $depth),
            $value instanceof Traversable => $this->list(iterator_to_array($value), $depth),
            default => null,
        };
    }

    /**
     * A relation's value (query, collection or list): every element is a
     * relation hop, so it passes the gate and spends depth, and becomes an id
     * once depth runs out. This holds even for elements that happen to be nested
     * entries elsewhere: what counts is how they were reached.
     *
     * @return list<mixed>
     */
    public function relations(mixed $value, int $depth): array
    {
        $elements = array_values(array_filter($this->elementList($value), $this->allowed(...)));

        return array_map(fn (ElementInterface $e) => $depth > 0 ? $this->element($e, $depth - 1) : $e->id, $elements);
    }

    /**
     * The owner's own nested elements (Matrix entries, a content block, CKEditor
     * entries), reached through one of its element-container fields. They're part
     * of the owner, which has already passed the gate, so they don't spend depth.
     *
     * @return list<array<string, mixed>>
     */
    public function nested(mixed $value, int $depth): array
    {
        return array_map(fn (ElementInterface $e) => $this->element($e, $depth), $this->elementList($value));
    }

    /**
     * A single related element (e.g. a link's target): gated, spends depth, null
     * when it's gated out. Pass `nested: true` only for the owner's own nested element.
     *
     * @return array<string, mixed>|int|null
     */
    public function one(?ElementInterface $element, int $depth, bool $nested = false): array|int|null
    {
        if ($element === null) {
            return null;
        }

        if ($nested) {
            return $this->element($element, $depth);
        }

        return $this->relations([$element], $depth)[0] ?? null;
    }

    /**
     * Eager-loads, level by level, every relation and nested-element field the
     * elements' layouts will serialise, so a page costs a query per field per
     * level rather than per element. Elements are grouped by type and layout,
     * since Craft maps an eager-loading handle through one layout's field.
     *
     * @param  list<ElementInterface>  $elements
     * @param  list<string>|null  $only  Limit the first level to these handles.
     */
    public function prepare(array $elements, int $depth, bool $root = false, ?array $only = null): void
    {
        if (! $this->eagerLoad) {
            return;
        }

        $groups = [];

        foreach ($elements as $element) {
            // Live preview may carry unsaved values; eager loading would replace them.
            if ($element->id === null || ($element instanceof Entry && $element->previewing)) {
                continue;
            }

            $groups[$element::class.'|'.($element->getFieldLayout()->id ?? 0)][] = $element;
        }

        $nested = [];
        $related = [];

        foreach ($groups as $group) {
            $handles = $this->eagerHandles($group[0], $root, $only);

            if ($handles === []) {
                continue;
            }

            try {
                Elements::eagerLoadElements($group[0]::class, collect($group), array_values($handles));
            } catch (Throwable $e) {
                // Eager loading is an optimisation: serialising lazily still works.
                report($e);

                continue;
            }

            foreach ($group as $element) {
                foreach (array_keys($handles) as $handle) {
                    $container = $element->getFieldLayout()?->getFieldByHandle($handle) instanceof ElementContainerFieldInterface;

                    foreach ($element->getEagerLoadedElements($handle) ?? [] as $child) {
                        if ($container) {
                            $nested[] = $child;
                        } elseif ($depth > 0 && $this->allowed($child)) {
                            $related[] = $child;
                        }
                    }
                }
            }
        }

        if ($nested !== []) {
            $this->prepare($nested, $depth);
        }

        if ($related !== []) {
            $this->prepare($related, $depth - 1);
        }
    }

    /**
     * @param  list<string>|null  $only
     * @return array<string, string|array{0: string, 1: array<string, mixed>}> handle => eager-loading path
     */
    private function eagerHandles(ElementInterface $element, bool $root, ?array $only): array
    {
        $layout = $element->getFieldLayout();

        if ($layout === null) {
            return [];
        }

        $qualifier = self::qualifier($element);
        $handles = [];

        foreach ($layout->getCustomFields() as $field) {
            $handle = $field->handle;

            if (
                $handle === null
                || ! $field instanceof EagerLoadingFieldInterface
                || ($only !== null && ! in_array($handle, $only, true))
                || $this->rules->excludes($handle, $qualifier)
                || $this->transformers?->for($field) instanceof OmitTransformer
                || ($root && $only === null && $this->defer && $this->rules->defers($handle, $qualifier))
            ) {
                continue;
            }

            $handles[$handle] = $field instanceof AssetsField && $this->assetTransforms !== []
                ? [$handle, ['withTransforms' => $this->assetTransforms]]
                : $handle;
        }

        return $handles;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function list(array $values, int $depth): array
    {
        if ($values !== [] && array_all($values, fn ($v) => $v instanceof ElementInterface)) {
            return $this->relations($values, $depth);
        }

        return array_map(fn ($v) => $this->value($v, $depth), $values);
    }

    /**
     * @return list<ElementInterface>
     */
    private function elementList(mixed $value): array
    {
        $items = match (true) {
            $value instanceof ElementInterface => [$value],
            $value instanceof ElementQueryInterface => $value->all(),
            $value instanceof Collection => $value->all(),
            is_iterable($value) => iterator_to_array($value, false),
            default => [],
        };

        return array_values(array_filter($items, fn ($e) => $e instanceof ElementInterface));
    }

    private function allowed(ElementInterface $element): bool
    {
        return $this->allow === null || ($this->allow)($element);
    }

    /**
     * @return array<string, string|null>|null
     */
    private function transforms(Asset $asset): ?array
    {
        if ($this->assetTransforms === [] || $asset->kind !== 'image') {
            return null;
        }

        $urls = [];

        foreach ($this->assetTransforms as $handle) {
            try {
                $urls[$handle] = $asset->getUrl($handle);
            } catch (Throwable $e) {
                report($e);
                $urls[$handle] = null;
            }
        }

        return $urls;
    }

    /**
     * The model's attributes the element exposes, less `$hidden` (and limited
     * to `$visible` when the model sets it) — what the model's own
     * serialisation would show.
     *
     * @param  EloquentElement<Model>  $element
     * @return array<string, mixed>
     */
    private function modelAttributes(EloquentElement $element): array
    {
        $model = $element->getModel();
        $names = $element::listedModelAttributes();
        $visible = $model->getVisible();

        if ($visible !== []) {
            $names = array_values(array_intersect($names, $visible));
        }

        $attributes = [];

        foreach (array_diff($names, $model->getHidden()) as $name) {
            $attributes[$name] = $this->value($model->getAttribute($name), 0);
        }

        return $attributes;
    }

    /**
     * The handle field rules can be qualified with: the entry type, or the
     * asset's volume.
     */
    public static function qualifier(ElementInterface $element): ?string
    {
        return match (true) {
            $element instanceof Entry => $element->getType()->handle,
            $element instanceof Asset => $element->getVolume()->handle,
            default => null,
        };
    }
}
