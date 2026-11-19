<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Text, numbers, booleans and dates (ISO 8601 strings). */
final class ScalarTransformer implements FieldTransformer
{
    public function __construct(private readonly string $type) {}

    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $serializer->value($value, 0);
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return $this->type;
    }
}
