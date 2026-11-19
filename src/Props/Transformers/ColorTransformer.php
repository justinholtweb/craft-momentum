<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\ColorData;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Color: `{hex, label}` or null. */
final class ColorTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $value instanceof ColorData ? ['hex' => $value->getHex(), 'label' => $value->label] : null;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return '{ hex: string; label: string | null } | null';
    }
}
