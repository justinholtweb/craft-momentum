<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\BaseOptionsField;
use CraftCms\Cms\Field\Checkboxes;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\MultiOptionsFieldData;
use CraftCms\Cms\Field\Data\OptionData;
use CraftCms\Cms\Field\MultiSelect;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/**
 * Dropdowns, radio buttons and button groups: `{value, label}` or null.
 * Checkboxes and multi-selects: a list of the selected `{value, label}`.
 */
final class OptionsTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        if ($value instanceof MultiOptionsFieldData) {
            return array_values(array_map(self::option(...), array_filter(
                iterator_to_array($value),
                fn (OptionData $o) => $o->value !== null && $o->value !== '',
            )));
        }

        if ($value instanceof OptionData) { // SingleOptionFieldData
            return $value->value === null || $value->value === '' ? null : self::option($value);
        }

        return self::isMulti($field) ? [] : null;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        $values = [];

        if ($field instanceof BaseOptionsField) {
            foreach ($field->options as $option) {
                if (isset($option['value']) && $option['value'] !== '') {
                    $values[] = TypeScriptWriter::literal((string) $option['value']);
                }
            }
        }

        $option = '{ value: '.($values === [] ? 'string' : implode(' | ', array_unique($values))).'; label: string }';

        return self::isMulti($field) ? "Array<{$option}>" : "{$option} | null";
    }

    /**
     * @return array{value: string, label: string|null}
     */
    private static function option(OptionData $option): array
    {
        return ['value' => (string) $option->value, 'label' => $option->label];
    }

    private static function isMulti(FieldInterface $field): bool
    {
        return $field instanceof Checkboxes || $field instanceof MultiSelect;
    }
}
