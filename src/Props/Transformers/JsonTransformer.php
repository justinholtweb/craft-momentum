<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\JsonData;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** The decoded JSON value. */
final class JsonTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $serializer->value($value instanceof JsonData ? $value->getValue() : $value, 0);
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return 'unknown';
    }
}
