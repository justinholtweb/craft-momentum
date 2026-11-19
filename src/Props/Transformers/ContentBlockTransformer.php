<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Field\ContentBlock;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Content Block: `{id, fields}` from the block's own field layout. */
final class ContentBlockTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        if ($value instanceof ElementInterface) {
            return $serializer->one($value, $depth);
        }

        $first = $serializer->relations($value, $depth)[0] ?? null;

        return is_array($first) ? $first : null;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        if (! $field instanceof ContentBlock) {
            return 'unknown';
        }

        return '{ id: number; fields: '.$writer->fieldsOf($field->getFieldLayout(), null).' } | null';
    }
}
