<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Contracts\FieldInterface;
use JustinHoltWeb\Momentum\Props\Omit;
use JustinHoltWeb\Momentum\Props\PropSerializer;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

/**
 * Turns one field type's value into JSON-safe props, and describes that shape
 * as a TypeScript type for `craft:momentum:types`.
 *
 * Everything a transformer returns is sent to the browser. Return
 * {@see Omit::Field} to leave the field out.
 */
interface FieldTransformer
{
    /**
     * @param  int  $depth  Relation hops still allowed (see {@see PropSerializer}).
     */
    public function transform(FieldInterface $field, mixed $value, PropSerializer $serializer, int $depth): mixed;

    public function typeScript(FieldInterface $field, TypeScriptWriter $writer): string;
}
