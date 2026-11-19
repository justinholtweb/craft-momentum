<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use JustinHoltWeb\Momentum\Props\Omit;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Leaves the field out of props (used for Addresses: personal data). */
final class OmitTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): Omit
    {
        return Omit::Field;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return 'never';
    }
}
