<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\IconData;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Icon: `{name, set}` or null (render the SVG client-side from your own icon set). */
final class IconTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $value instanceof IconData ? ['name' => $value->name, 'set' => $value->set] : null;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return '{ name: string; set: string | null } | null';
    }
}
