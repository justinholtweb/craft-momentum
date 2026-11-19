<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Matrix;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/**
 * Matrix: the field's nested entries in order, each with its entry `type`
 * handle and fields. Nested entries are part of their owner, so they don't
 * spend relation depth.
 */
final class NestedEntriesTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $serializer->relations($value, $depth);
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        if (! $field instanceof Matrix) {
            return 'Array<unknown>';
        }

        $types = array_map($writer->entryType(...), $field->getEntryTypes());

        return 'Array<'.($types === [] ? 'MomentumEntry' : implode(' | ', array_unique($types))).'>';
    }
}
