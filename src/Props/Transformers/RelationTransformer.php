<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/**
 * Entries, Assets, Users and Eloquent relation fields: a list of related
 * elements while depth lasts, then a list of ids.
 */
final class RelationTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $serializer->relations($value, $depth);
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return $field instanceof BaseRelationField
            ? 'Array<'.$writer->relationTarget($field).' | number>'
            : 'Array<unknown>';
    }
}
