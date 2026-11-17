<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props;

use BackedEnum;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\User\Elements\User;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;
use Traversable;
use UnitEnum;

/**
 * Turns elements into plain arrays for Inertia page props.
 *
 * Everything here ends up in the page's public JSON, so the rules are
 * conservative: users expose only an id and a display name, element types we
 * don't know expose only id/title/url, and values we can't represent become null
 * rather than leaking an object's internals.
 *
 * Depth counts relation hops. Nested entries (Matrix and other owned blocks) are
 * part of their owner, so they don't spend depth; related elements do. At depth
 * 0 a relation becomes a list of ids.
 */
class PropSerializer
{
    /**
     * @param  list<string>  $excludeFields  Field handles never serialised.
     */
    public function __construct(
        private readonly int $depth = 2,
        private readonly array $excludeFields = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function element(ElementInterface $element, ?int $depth = null): array
    {
        $depth ??= $this->depth;

        if ($element instanceof User) {
            return ['id' => $element->id, 'name' => $element->getFriendlyName() ?? $element->getName()];
        }

        $data = [
            'id' => $element->id,
            'uid' => $element->uid,
            'title' => $element->title,
            'slug' => $element->slug,
            'uri' => $element->uri,
            'url' => $element->getUrl(),
        ];

        if ($element instanceof Entry) {
            $data += [
                'section' => $element->getSection()?->handle,
                'type' => $element->getType()->handle,
                'postDate' => $this->value($element->postDate, 0),
                'dateUpdated' => $this->value($element->dateUpdated, 0),
            ];
        } elseif ($element instanceof Asset) {
            $data += [
                'url' => $element->getUrl(),
                'alt' => $element->alt,
                'filename' => $element->getFilename(),
                'kind' => $element->kind,
                'width' => $element->getWidth(),
                'height' => $element->getHeight(),
            ];
        } else {
            // Unknown element types (addresses, third-party elements) can carry
            // personal data; expose only what any public link would.
            return array_intersect_key($data, array_flip(['id', 'title', 'url']));
        }

        $data['fields'] = $this->fields($element, $depth);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(ElementInterface $element, int $depth): array
    {
        $layout = $element->getFieldLayout();

        if ($layout === null) {
            return [];
        }

        $fields = [];

        foreach ($layout->getVisibleCustomFields($element) as $field) {
            $handle = $field->handle;

            if ($handle === null || in_array($handle, $this->excludeFields, true)) {
                continue;
            }

            $fields[$handle] = $this->value($element->getFieldValue($handle), $depth);
        }

        return $fields;
    }

    public function value(mixed $value, int $depth): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof ElementInterface => $this->related($value, $depth),
            $value instanceof ElementQuery => $this->elements(array_values($value->collect()->all()), $depth),
            $value instanceof Stringable => (string) $value,
            $value instanceof JsonSerializable => $this->value($value->jsonSerialize(), $depth),
            $value instanceof Arrayable => $this->value($value->toArray(), $depth),
            is_array($value) => $this->list($value, $depth),
            $value instanceof Traversable => $this->list(iterator_to_array($value), $depth),
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function list(array $values, int $depth): array
    {
        if ($values !== [] && array_all($values, fn ($v) => $v instanceof ElementInterface)) {
            return $this->elements(array_values($values), $depth);
        }

        return array_map(fn ($v) => $this->value($v, $depth), $values);
    }

    /**
     * @param  list<mixed>  $elements
     * @return list<mixed>
     */
    private function elements(array $elements, int $depth): array
    {
        if ($depth <= 0 && ! $this->allNested($elements)) {
            return array_map(fn (ElementInterface $e) => $e->id, $elements);
        }

        return array_map(fn (ElementInterface $e) => $this->related($e, $depth), $elements);
    }

    /**
     * @return array<string, mixed>|int|null
     */
    private function related(ElementInterface $element, int $depth): array|int|null
    {
        if ($this->isNested($element)) {
            return $this->element($element, $depth);
        }

        return $depth > 0 ? $this->element($element, $depth - 1) : $element->id;
    }

    /**
     * @param  list<mixed>  $elements
     */
    private function allNested(array $elements): bool
    {
        return $elements !== [] && array_all($elements, fn ($e) => $e instanceof ElementInterface && $this->isNested($e));
    }

    private function isNested(ElementInterface $element): bool
    {
        return $element instanceof Entry && $element->getField() !== null;
    }
}
