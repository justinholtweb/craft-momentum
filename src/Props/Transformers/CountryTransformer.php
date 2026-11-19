<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CommerceGuys\Addressing\Country\Country;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/** Country: `{code, name}` (name in the current locale) or null. */
final class CountryTransformer implements FieldTransformer
{
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed
    {
        return $value instanceof Country ? ['code' => $value->getCountryCode(), 'name' => $value->getName()] : null;
    }

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string
    {
        return '{ code: string; name: string } | null';
    }
}
